<?php
/**
 * Mediamora Toolkit, module: Verweesde meta opruimen
 * Wordt alleen geladen als de module aanstaat.
 *
 * Aanleiding: sinds 22 oktober 2026 draait in MainWP (Maintenance-extensie)
 * elke dinsdag rond 05:00 een database-opschoning: revisies boven de 20,
 * automatische concepten, spam- en prullenbakreacties en verlopen transients
 * gaan weg. MainWP Child 6.3 doet dat met kale DELETE-queries op wp_posts en
 * wp_comments (maintenance_db() en maintenance_delete_revisions() in
 * class-mainwp-child-maintenance.php), zonder wp_delete_post_revision() of
 * wp_delete_comment(). De bijbehorende regels in wp_postmeta en
 * wp_commentmeta blijven daardoor staan. Op karinedebruijn.com bleef na de
 * eerste ronde 40.968 regels postmeta over, 89 MB, waarvan 88 MB
 * _elementor_data van verwijderde revisies. Het schema blijft in MainWP,
 * omdat het dan in de Pro Reports komt; deze module ruimt erachteraan op.
 *
 * Wat hij doet, elke woensdag (de dag na MainWP):
 *
 * - wp_postmeta: regels weg waarvan de post_id niet meer in wp_posts staat.
 *   post_id 0 blijft staan, die gebruiken sommige plugins bewust.
 * - wp_commentmeta: regels weg waarvan de comment_id niet meer in
 *   wp_comments staat. Ook hier blijft 0 staan.
 * - wp_term_relationships: alleen tellen, niets weghalen. link_category telt
 *   niet mee, want die hoort bij wp_links en niet bij wp_posts.
 *
 * Nooit OPTIMIZE TABLE: dat zet een lock op de tabel. De ruimte komt bij een
 * volgende insert weer in gebruik en de dump in de back-up wordt sowieso
 * kleiner.
 *
 * Gedeelde hosting: de module loopt de tabel in vensters van 50.000 meta_id's
 * op volgorde van de primaire sleutel door, haalt per venster hooguit 5.000
 * verweesde id's op met een LEFT JOIN en verwijdert die met DELETE ... WHERE
 * meta_id IN (...). Elke query blijft zo kort. Na elke DELETE een korte
 * pauze. Is het tijdsbudget van de ronde op, dan plant hij een vervolgronde
 * in en gaat daar verder waar hij was.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MM_META_OPRUIMEN_OPTIE   = 'mm_meta_opruimen_laatste';
const MM_META_OPRUIMEN_TELLING = 'mm_meta_opruimen_telling';
const MM_META_OPRUIMEN_SLOT    = 'mm_meta_opruimen_slot';
const MM_META_OPRUIMEN_VENSTER = 50000; // meta_id's per query
const MM_META_OPRUIMEN_BATCH   = 5000;  // hooguit zoveel id's per DELETE
const MM_META_OPRUIMEN_BUDGET  = 15;    // seconden per ronde
const MM_META_OPRUIMEN_PAUZE   = 200000; // microseconden na elke DELETE


/* -------------------------------------------------------------------------
 * Inplannen
 *
 * Staat het wekelijkse event er niet, dan is de module net aangezet: na een
 * update, na het aanzetten van de schakelaar of na opnieuw activeren (bij
 * uitzetten en deactiveren gaan de events weg, zie mediamora-toolkit.php).
 * Dan komt het wekelijkse event erin. Er wordt bij het aanzetten niet meteen
 * opgeruimd: de eerste ronde is de eerstvolgende woensdag. Tot die tijd kan
 * "Nu tellen" laten zien wat er weg zou gaan.
 *
 * Het tijdstip is woensdag 05:00 in de tijdzone van de site, plus een vaste
 * verschuiving van 0 tot 59 minuten per site. Zo begint niet elke site op
 * dezelfde server tegelijk, en vallen twee gelijktijdige verzoeken op
 * hetzelfde tijdstip, zodat er geen dubbel event ontstaat.
 * ---------------------------------------------------------------------- */

add_action( 'init', 'mm_meta_opruimen_inplannen' );

function mm_meta_opruimen_inplannen() {
	if ( wp_next_scheduled( 'mm_meta_opruimen_wekelijks' ) ) {
		return;
	}
	wp_schedule_event( mm_meta_opruimen_volgende_woensdag(), 'weekly', 'mm_meta_opruimen_wekelijks' );
}

/**
 * Eerstvolgende woensdag 05:00 plus de verschuiving van deze site, als
 * timestamp.
 */
function mm_meta_opruimen_volgende_woensdag() {
	$moment = new DateTime( 'wednesday 05:00', wp_timezone() );
	$moment->modify( '+' . ( absint( crc32( home_url() ) ) % 60 ) . ' minutes' );
	if ( $moment->getTimestamp() <= time() ) {
		$moment->modify( '+7 days' );
	}
	return $moment->getTimestamp();
}

add_action( 'mm_meta_opruimen_wekelijks', 'mm_meta_opruimen_wekelijks' );
add_action( 'mm_meta_opruimen_vervolg', 'mm_meta_opruimen_vervolg' );

/**
 * De wekelijkse ronde begint altijd opnieuw. Is er nog een ronde van vorige
 * week bezig, dan is de vervolgronde blijkbaar zoekgeraakt; de nieuwe ronde
 * loopt dan gewoon alles opnieuw door.
 */
function mm_meta_opruimen_wekelijks() {
	mm_meta_opruimen_draai( true );
}

/**
 * Vervolgronde. Doet niets als er geen ronde meer bezig is, dus een
 * overgebleven vervolgevent begint nooit zelf een nieuwe ronde.
 */
function mm_meta_opruimen_vervolg() {
	$stand = get_option( MM_META_OPRUIMEN_OPTIE, array() );
	if ( is_array( $stand ) && isset( $stand['status'] ) && 'bezig' === $stand['status'] ) {
		mm_meta_opruimen_draai( false );
	}
}


/* -------------------------------------------------------------------------
 * De ronde
 * ---------------------------------------------------------------------- */

/**
 * De twee tabellen die opgeruimd worden, met hun koppeling.
 */
function mm_meta_opruimen_tabellen() {
	global $wpdb;
	return array(
		'postmeta'    => array(
			'meta'    => $wpdb->postmeta,
			'kolom'   => 'post_id',
			'ouder'   => $wpdb->posts,
			'sleutel' => 'ID',
		),
		'commentmeta' => array(
			'meta'    => $wpdb->commentmeta,
			'kolom'   => 'comment_id',
			'ouder'   => $wpdb->comments,
			'sleutel' => 'comment_ID',
		),
	);
}

/**
 * Een ronde opruimen, tot alles door is of het tijdsbudget op is.
 *
 * @param bool $nieuw True: vooraan beginnen. False: verder waar de vorige
 *                    ronde stopte.
 */
function mm_meta_opruimen_draai( $nieuw ) {
	global $wpdb;

	if ( ! mm_meta_opruimen_slot_nemen() ) {
		return;
	}

	$deadline = microtime( true ) + MM_META_OPRUIMEN_BUDGET;
	$stand    = get_option( MM_META_OPRUIMEN_OPTIE, array() );

	if ( $nieuw || ! is_array( $stand ) || empty( $stand['status'] ) || 'bezig' !== $stand['status'] ) {
		$stand = array(
			'status'   => 'bezig',
			'compleet' => false,
			'start'    => time(),
			'eind'     => 0,
			'rondes'   => 0,
			'fout'     => '',
			'tabellen' => array(),
			'termen'   => null,
		);
		// Tot waar deze ronde loopt: regels die er later bij komen horen bij
		// posts en reacties die nog bestaan.
		foreach ( mm_meta_opruimen_tabellen() as $naam => $t ) {
			$stand['tabellen'][ $naam ] = array(
				'cursor' => 0,
				'tot'    => (int) $wpdb->get_var( "SELECT MAX(meta_id) FROM {$t['meta']}" ),
				'regels' => 0,
				'bytes'  => 0,
			);
		}
	}

	$stand['rondes']++;
	$klaar = true;

	// Vangnet: wordt PHP halverwege afgekapt, dan gaat deze vervolgronde
	// verder als het slot is vervallen. Gaat alles goed en is de ronde nog
	// niet klaar, dan is dit ook gewoon de volgende ronde.
	wp_schedule_single_event( time() + 6 * MINUTE_IN_SECONDS, 'mm_meta_opruimen_vervolg' );

	foreach ( mm_meta_opruimen_tabellen() as $naam => $t ) {
		$tabel = $stand['tabellen'][ $naam ];
		$stop  = false;

		while ( $tabel['cursor'] < $tabel['tot'] ) {

			if ( microtime( true ) >= $deadline ) {
				$klaar = false;
				$stop  = true;
				break;
			}

			$tot = min( $tabel['cursor'] + MM_META_OPRUIMEN_VENSTER, $tabel['tot'] );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabel- en kolomnamen komen uit $wpdb.
			$rijen = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT m.meta_id, LENGTH(m.meta_key) + IFNULL(LENGTH(m.meta_value), 0) AS bytes
					FROM {$t['meta']} m
					LEFT JOIN {$t['ouder']} o ON o.{$t['sleutel']} = m.{$t['kolom']}
					WHERE m.meta_id > %d AND m.meta_id <= %d AND m.{$t['kolom']} <> 0 AND o.{$t['sleutel']} IS NULL
					ORDER BY m.meta_id
					LIMIT %d",
					$tabel['cursor'],
					$tot,
					MM_META_OPRUIMEN_BATCH
				)
			);

			if ( $wpdb->last_error ) {
				$stand['fout'] = $wpdb->last_error;
				$stop          = true;
				break;
			}

			if ( ! $rijen ) {
				$tabel['cursor'] = $tot;
				continue;
			}

			$ids   = array_map( 'intval', wp_list_pluck( $rijen, 'meta_id' ) );
			$bytes = array_sum( array_map( 'intval', wp_list_pluck( $rijen, 'bytes' ) ) );

			// De id's zijn net als int uit de database gekomen. De koppeling
			// staat nog eens in de WHERE, zodat alleen weggaat wat op dit moment
			// nog steeds nergens bij hoort.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$weg = $wpdb->query(
				"DELETE m FROM {$t['meta']} m
				LEFT JOIN {$t['ouder']} o ON o.{$t['sleutel']} = m.{$t['kolom']}
				WHERE m.meta_id IN (" . implode( ',', $ids ) . ") AND o.{$t['sleutel']} IS NULL"
			);

			if ( false === $weg ) {
				$stand['fout'] = $wpdb->last_error ? $wpdb->last_error : 'DELETE mislukt';
				$stop          = true;
				break;
			}

			$tabel['regels'] += (int) $weg;
			// Gaat er minder weg dan er gevonden is, dan is de schatting iets te
			// hoog. Dat is het ook maar.
			$tabel['bytes'] += $bytes;

			// Een volle batch: in hetzelfde venster verder na de laatste id.
			$tabel['cursor'] = count( $ids ) < MM_META_OPRUIMEN_BATCH ? $tot : max( $ids );

			// Tussenstand bewaren, zodat een afgekapte ronde niet opnieuw telt.
			$stand['tabellen'][ $naam ] = $tabel;
			update_option( MM_META_OPRUIMEN_OPTIE, $stand, false );

			usleep( MM_META_OPRUIMEN_PAUZE );
		}

		$stand['tabellen'][ $naam ] = $tabel;
		if ( $stop ) {
			break;
		}
	}

	if ( $stand['fout'] ) {
		$stand['status'] = 'fout';
		$stand['eind']   = time();
	} elseif ( $klaar ) {
		$stand['status']   = 'klaar';
		$stand['compleet'] = true;
		$stand['eind']     = time();
		$stand['termen']   = mm_meta_opruimen_tel_termen();
	}

	// Klaar of fout: het vangnet is niet meer nodig.
	if ( 'bezig' !== $stand['status'] ) {
		wp_clear_scheduled_hook( 'mm_meta_opruimen_vervolg' );
	}

	update_option( MM_META_OPRUIMEN_OPTIE, $stand, false );
	mm_meta_opruimen_slot_vrijgeven();
}

/**
 * Slot tegen twee rondes tegelijk, bijvoorbeeld als WP-cron twee keer
 * start. add_option faalt als de optie al bestaat, dus maar één verzoek
 * krijgt het. Een slot van een ronde die is afgekapt vervalt na vijf
 * minuten; het vangnet in mm_meta_opruimen_draai() komt daarna.
 */
function mm_meta_opruimen_slot_nemen() {
	if ( add_option( MM_META_OPRUIMEN_SLOT, time(), '', false ) ) {
		return true;
	}
	$sinds = (int) get_option( MM_META_OPRUIMEN_SLOT, 0 );
	if ( $sinds && $sinds > time() - 5 * MINUTE_IN_SECONDS ) {
		return false;
	}
	update_option( MM_META_OPRUIMEN_SLOT, time(), false );
	return true;
}

function mm_meta_opruimen_slot_vrijgeven() {
	delete_option( MM_META_OPRUIMEN_SLOT );
}


/* -------------------------------------------------------------------------
 * Tellen
 * ---------------------------------------------------------------------- */

/**
 * Verweesde regels in wp_term_relationships, zonder link_category. Een
 * regel waarvan ook de term_taxonomy weg is, telt mee.
 */
function mm_meta_opruimen_tel_termen() {
	global $wpdb;
	$aantal = $wpdb->get_var(
		"SELECT COUNT(*)
		FROM {$wpdb->term_relationships} r
		LEFT JOIN {$wpdb->posts} p ON p.ID = r.object_id
		LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = r.term_taxonomy_id
		WHERE p.ID IS NULL AND ( tt.taxonomy IS NULL OR tt.taxonomy <> 'link_category' )"
	);
	return null === $aantal ? null : (int) $aantal;
}

/**
 * Telt wat een ronde zou weghalen, zonder iets te verwijderen. In dezelfde
 * vensters als de ronde, met een eigen tijdsbudget. Is dat op, dan is de
 * telling onvolledig en staat dat erbij.
 */
function mm_meta_opruimen_tel() {
	global $wpdb;

	$deadline = microtime( true ) + 20;
	$telling  = array(
		'datum'    => time(),
		'compleet' => true,
		'tabellen' => array(),
		'termen'   => null,
	);

	foreach ( mm_meta_opruimen_tabellen() as $naam => $t ) {
		$tot    = (int) $wpdb->get_var( "SELECT MAX(meta_id) FROM {$t['meta']}" );
		$cursor = 0;
		$regels = 0;
		$bytes  = 0;

		while ( $cursor < $tot ) {
			if ( microtime( true ) >= $deadline ) {
				$telling['compleet'] = false;
				break;
			}
			$tot_venster = min( $cursor + MM_META_OPRUIMEN_VENSTER, $tot );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rij = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT COUNT(*) AS regels, IFNULL(SUM(LENGTH(m.meta_key) + IFNULL(LENGTH(m.meta_value), 0)), 0) AS bytes
					FROM {$t['meta']} m
					LEFT JOIN {$t['ouder']} o ON o.{$t['sleutel']} = m.{$t['kolom']}
					WHERE m.meta_id > %d AND m.meta_id <= %d AND m.{$t['kolom']} <> 0 AND o.{$t['sleutel']} IS NULL",
					$cursor,
					$tot_venster
				)
			);
			if ( ! $rij ) {
				$telling['compleet'] = false;
				break;
			}
			$regels += (int) $rij->regels;
			$bytes  += (int) $rij->bytes;
			$cursor  = $tot_venster;
		}

		$telling['tabellen'][ $naam ] = array(
			'regels' => $regels,
			'bytes'  => $bytes,
		);
	}

	$telling['termen'] = mm_meta_opruimen_tel_termen();

	update_option( MM_META_OPRUIMEN_TELLING, $telling, false );

	return $telling;
}

add_action( 'admin_post_mm_meta_opruimen_tel', 'mm_meta_opruimen_tel_knop' );

function mm_meta_opruimen_tel_knop() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Geen toegang.' );
	}
	check_admin_referer( 'mm_meta_opruimen_tel' );

	mm_meta_opruimen_tel();

	wp_safe_redirect( admin_url( 'options-general.php?page=' . MM_TOOLKIT_SLUG . '&mm_meta_geteld=1#mm-meta-opruimen' ) );
	exit;
}


/* -------------------------------------------------------------------------
 * Instellingenscherm
 *
 * mm_toolkit_scherm() roept deze functies aan als de module laadt: de
 * korte regel in de statuskolom en het overzicht onder de tabel. Het
 * overzicht staat buiten het formulier van de schakelaars, want de knop
 * "Nu tellen" heeft een eigen formulier.
 * ---------------------------------------------------------------------- */

function mm_meta_opruimen_mb( $bytes ) {
	return number_format_i18n( $bytes / MB_IN_BYTES, 1 ) . ' MB';
}

function mm_meta_opruimen_datum( $timestamp ) {
	return wp_date( 'j F Y, H:i', $timestamp );
}

/**
 * Korte regel voor de statuskolom.
 */
function mm_meta_opruimen_status_regel() {
	$stand = get_option( MM_META_OPRUIMEN_OPTIE, array() );
	if ( ! is_array( $stand ) || empty( $stand['status'] ) ) {
		$volgende = wp_next_scheduled( 'mm_meta_opruimen_wekelijks' );
		return $volgende ? 'Eerste ronde ' . esc_html( mm_meta_opruimen_datum( $volgende ) ) : 'Nog niet gedraaid';
	}
	if ( 'bezig' === $stand['status'] ) {
		return 'Ronde bezig';
	}
	$regels = 0;
	$bytes  = 0;
	foreach ( $stand['tabellen'] as $tabel ) {
		$regels += $tabel['regels'];
		$bytes  += $tabel['bytes'];
	}
	$tekst = 'Laatste ronde ' . wp_date( 'j M', $stand['eind'] ) . ': ' . number_format_i18n( $regels ) . ' regels, ' . mm_meta_opruimen_mb( $bytes );
	return 'fout' === $stand['status'] ? '<span style="color:#b32d2e;">' . esc_html( $tekst . ', afgebroken' ) . '</span>' : esc_html( $tekst );
}

/**
 * Het overzicht onder de tabel.
 */
function mm_meta_opruimen_overzicht() {

	$stand   = get_option( MM_META_OPRUIMEN_OPTIE, array() );
	$telling = get_option( MM_META_OPRUIMEN_TELLING, array() );
	$namen   = array(
		'postmeta'    => 'wp_postmeta',
		'commentmeta' => 'wp_commentmeta',
	);

	echo '<h2 id="mm-meta-opruimen" style="margin-top:32px;">Verweesde meta</h2>';

	if ( ! empty( $_GET['mm_meta_geteld'] ) ) {
		echo '<div class="notice notice-success inline"><p>Geteld. Er is niets verwijderd.</p></div>';
	}

	// Laatste ronde.
	echo '<h3>Laatste ronde</h3>';
	if ( ! is_array( $stand ) || empty( $stand['status'] ) ) {
		echo '<p>Nog niet gedraaid.</p>';
	} else {
		echo '<table class="widefat striped" style="max-width:900px;"><thead><tr><th>Tabel</th><th>Verwijderd</th><th>Geschat</th></tr></thead><tbody>';
		foreach ( $namen as $sleutel => $naam ) {
			$tabel = isset( $stand['tabellen'][ $sleutel ] ) ? $stand['tabellen'][ $sleutel ] : array( 'regels' => 0, 'bytes' => 0 );
			echo '<tr><td><code>' . esc_html( $naam ) . '</code></td><td>' . esc_html( number_format_i18n( $tabel['regels'] ) ) . ' regels</td><td>' . esc_html( mm_meta_opruimen_mb( $tabel['bytes'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<p>Gestart ' . esc_html( mm_meta_opruimen_datum( $stand['start'] ) );
		if ( 'klaar' === $stand['status'] ) {
			echo ', compleet ' . esc_html( mm_meta_opruimen_datum( $stand['eind'] ) );
		} elseif ( 'fout' === $stand['status'] ) {
			echo ', <span style="color:#b32d2e;">niet compleet: afgebroken door een databasefout (' . esc_html( $stand['fout'] ) . ')</span>';
		} else {
			$volgende = wp_next_scheduled( 'mm_meta_opruimen_vervolg' );
			echo ', nog bezig' . ( $volgende ? ', gaat verder om ' . esc_html( wp_date( 'H:i', $volgende ) ) : '' );
		}
		echo ', in ' . esc_html( number_format_i18n( $stand['rondes'] ) ) . ' ' . ( 1 === (int) $stand['rondes'] ? 'deel' : 'delen' ) . '.';
		if ( null !== $stand['termen'] ) {
			echo ' Verweesde regels in <code>wp_term_relationships</code>: ' . esc_html( number_format_i18n( $stand['termen'] ) ) . ' (alleen geteld).';
		}
		echo '</p>';
	}

	$volgende = wp_next_scheduled( 'mm_meta_opruimen_wekelijks' );
	if ( $volgende ) {
		echo '<p style="color:#646970;">Volgende wekelijkse ronde: ' . esc_html( mm_meta_opruimen_datum( $volgende ) ) . '.</p>';
	}

	// Laatste telling.
	echo '<h3>Telling</h3>';
	if ( is_array( $telling ) && ! empty( $telling['datum'] ) ) {
		echo '<table class="widefat striped" style="max-width:900px;"><thead><tr><th>Tabel</th><th>Verweesd</th><th>Geschat</th></tr></thead><tbody>';
		foreach ( $namen as $sleutel => $naam ) {
			$tabel = $telling['tabellen'][ $sleutel ];
			echo '<tr><td><code>' . esc_html( $naam ) . '</code></td><td>' . esc_html( number_format_i18n( $tabel['regels'] ) ) . ' regels</td><td>' . esc_html( mm_meta_opruimen_mb( $tabel['bytes'] ) ) . '</td></tr>';
		}
		echo '<tr><td><code>wp_term_relationships</code></td><td>' . ( null === $telling['termen'] ? 'onbekend' : esc_html( number_format_i18n( $telling['termen'] ) ) . ' regels' ) . '</td><td>alleen geteld</td></tr>';
		echo '</tbody></table>';
		echo '<p>Geteld ' . esc_html( mm_meta_opruimen_datum( $telling['datum'] ) ) . '.';
		if ( empty( $telling['compleet'] ) ) {
			echo ' <span style="color:#b32d2e;">Onvolledig: het tellen duurde te lang en is halverwege gestopt.</span>';
		}
		echo '</p>';
	} else {
		echo '<p>Nog niet geteld.</p>';
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="mm_meta_opruimen_tel">';
	wp_nonce_field( 'mm_meta_opruimen_tel' );
	submit_button( 'Nu tellen', 'secondary', 'submit', false );
	echo ' <span style="color:#646970;">Telt alleen, verwijdert niets.</span>';
	echo '</form>';
}
