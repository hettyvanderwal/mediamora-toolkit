<?php
/**
 * Mediamora Toolkit, module: Afsluiten op einddatum
 *
 * Voor een opgezegd onderhoudspakket. Mediamora vult op het tabblad Mediamora
 * één einddatum in. Vanaf die datum (tijdzone van de site) doet de dagelijkse
 * cron-taak mm_einddatum_controle, zonder ingelogde gebruiker, in deze
 * volgorde:
 *
 *  1. WPvivid: de remote opslag, de back-upschema's en de koppeling met het
 *     WPvivid Pro-account wissen, en alle geplande taken van WPvivid afmelden.
 *  2. WPvivid Plugins Pro en WPvivid Backup Plugin deactiveren en verwijderen.
 *  3. MainWP Child Reports en daarna MainWP Child deactiveren en verwijderen.
 *  4. Een mail naar MM_MONITOR_ONTVANGER met het resultaat.
 *  5. De toolkit zelf deactiveren en verwijderen; uninstall.php ruimt op.
 *
 * Elke stap kijkt eerst naar de toestand en kan dus veilig nog een keer
 * draaien. Een transient-slot houdt een tweede cron-aanroep tegen die tegelijk
 * binnenkomt.
 *
 * Plugins die in dit verzoek actief waren, zijn met hun code nog geladen, ook
 * na deactiveren en verwijderen. Om te voorkomen dat die nog iets terugzetten,
 * gaat het na stap 2 en 3 verder in een nieuw verzoek (het event
 * mm_einddatum_vervolg, twee minuten later): daar draait stap 1 nog een keer,
 * ruimt de module de rest van WPvivid op, controleert alles en mailt.
 *
 * Geen directe schrijftoegang tot het bestandssysteem in de cron (geen
 * 'direct'-methode in WP_Filesystem): dan alleen deactiveren, dat in de mail
 * zetten, en de toolkit blijft staan om het de volgende nacht opnieuw te
 * proberen. Dezelfde uitkomst wordt maar één keer gemaild.
 *
 * Mislukt de mail, dan blijft de toolkit ook staan en probeert hij het de
 * volgende nacht opnieuw: hij verwijdert zichzelf pas als Mediamora de
 * eindstand in de mail heeft gekregen.
 *
 * Niet op multisite.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MM_EINDDATUM_OPTIE   = 'mm_einddatum';
const MM_EINDDATUM_STAND   = 'mm_einddatum_stand';
const MM_EINDDATUM_CRON    = 'mm_einddatum_controle';
const MM_EINDDATUM_VERVOLG = 'mm_einddatum_vervolg';
const MM_EINDDATUM_SLOT    = 'mm_einddatum_slot';
const MM_EINDDATUM_PROEF   = 'mm_einddatum_proef_';

// Status van een plugin. Weg is weg, hoe dan ook.
const MM_EINDDATUM_VERWIJDERD    = 'verwijderd';
const MM_EINDDATUM_NIET_GEVONDEN = 'niet gevonden';
const MM_EINDDATUM_GEDEACTIVEERD = 'gedeactiveerd';
const MM_EINDDATUM_MISLUKT       = 'mislukt';


/* -------------------------------------------------------------------------
 * Wat er weg moet
 * ---------------------------------------------------------------------- */

/**
 * De plugins, in de volgorde waarin ze weg gaan. Herkend op de naam in de
 * pluginkop (hoofdletters maken niet uit) of op het vaste pluginbestand, zodat
 * een plugin in een andere map ook gevonden wordt.
 *
 * @return array[]
 */
function mm_einddatum_doelen() {
	return array(
		'wpvivid_pro'          => array(
			'naam'      => 'WPvivid Plugins Pro',
			'namen'     => array( 'WPvivid Plugins Pro' ),
			'bestanden' => array( 'wpvivid-backup-pro/wpvivid-backup-pro.php' ),
		),
		'wpvivid'              => array(
			'naam'      => 'WPvivid Backup Plugin',
			'namen'     => array( 'WPvivid Backup Plugin' ),
			'bestanden' => array( 'wpvivid-backuprestore/wpvivid-backuprestore.php' ),
		),
		'mainwp_child_reports' => array(
			'naam'      => 'MainWP Child Reports',
			'namen'     => array( 'MainWP Child Reports' ),
			'bestanden' => array( 'mainwp-child-reports/mainwp-child-reports.php' ),
		),
		'mainwp_child'         => array(
			'naam'      => 'MainWP Child',
			'namen'     => array( 'MainWP Child' ),
			'bestanden' => array( 'mainwp-child/mainwp-child.php' ),
		),
	);
}

/**
 * De opties van WPvivid die in stap 1 weg gaan, per soort.
 *
 * Gratis plugin (WPvivid Backup Plugin, nagelopen in de broncode van 0.9.140):
 * - remote opslag: wpvivid_upload_setting, een array met per remote-id de
 *   instellingen ('type', 'name', bij S3 ook de sleutels en 'path'), en de
 *   gekozen remote in wpvivid_user_history['remote_selected'];
 * - schema: wpvivid_schedule_setting, met het event wpvivid_main_schedule_event;
 * - wpvivid_api_token en wpvivid_saved_api_token: de sleutels om een site naar
 *   een andere over te zetten.
 *
 * WPvivid Plugins Pro (nagelopen in de broncode en de database van 2.2.54):
 * - schema's: wpvivid_schedule_addon_setting, per schema met als cron-hook het
 *   eigen ID (wpvivid_schedule_event<id>, met het ID als argument), en de
 *   incrementele schema's in wpvivid_incremental_schedules, met files- en
 *   db_schedule_id als cron-hook;
 * - koppeling met het Pro-account: wpvivid_pro_user (het token),
 *   wpvivid_connect_key (de sleutel van de verbinding met de server van
 *   WPvivid) en wpvivid_dashboard_info (licentie en add-ons).
 *
 * Kopieën van de remote-instellingen staan in de gratis plugin per back-up in
 * wpvivid_backup_list en per taak in wpvivid_task_list, en in Pro per back-up
 * in wpvivid_new_remote_list. Die haalt mm_einddatum_wpvivid_kopieen() eruit,
 * net als die in andere lijsten.
 *
 * Wat hier mist, ruimt stap 1 toch op zodra beide WPvivid-plugins weg zijn:
 * dan gaan alle opties met wpvivid_ ervoor en de tabel wpvivid_options weg.
 *
 * @return string[][]
 */
function mm_einddatum_wpvivid_opties() {
	return array(
		'schema'   => array( 'wpvivid_schedule_setting', 'wpvivid_schedule_addon_setting', 'wpvivid_incremental_schedules' ),
		'account'  => array( 'wpvivid_pro_user', 'wpvivid_connect_key', 'wpvivid_dashboard_info' ),
		'sleutels' => array( 'wpvivid_api_token', 'wpvivid_saved_api_token' ),
	);
}


/* -------------------------------------------------------------------------
 * Datum en inplannen
 * ---------------------------------------------------------------------- */

/**
 * Is dit een bestaande datum in de vorm JJJJ-MM-DD?
 *
 * @param string $datum
 * @return bool
 */
function mm_einddatum_geldig( $datum ) {
	if ( ! is_string( $datum ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $datum, $m ) ) {
		return false;
	}
	return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
}

/**
 * De ingestelde einddatum, of een lege string.
 *
 * @return string
 */
function mm_einddatum_datum() {
	$datum = get_option( MM_EINDDATUM_OPTIE, '' );
	return mm_einddatum_geldig( $datum ) ? $datum : '';
}

/**
 * Vandaag in de tijdzone van de site, als JJJJ-MM-DD.
 *
 * @return string
 */
function mm_einddatum_vandaag() {
	return wp_date( 'Y-m-d' );
}

/**
 * Is de einddatum bereikt? JJJJ-MM-DD is als tekst te vergelijken.
 *
 * @param string $datum
 * @return bool
 */
function mm_einddatum_bereikt( $datum ) {
	return '' !== $datum && mm_einddatum_vandaag() >= $datum;
}

/**
 * De datum leesbaar, bijvoorbeeld 1 december 2026.
 *
 * @param string $datum JJJJ-MM-DD.
 * @return string
 */
function mm_einddatum_leesbaar( $datum ) {
	$moment = date_create_immutable_from_format( '!Y-m-d', $datum, wp_timezone() );
	return $moment ? wp_date( 'j F Y', $moment->getTimestamp() ) : $datum;
}

/**
 * Eerstvolgende 00:10 in de tijdzone van de site, zodat de controle vlak na
 * middernacht draait.
 *
 * @return int
 */
function mm_einddatum_volgende_nacht() {
	$nu     = new DateTimeImmutable( 'now', wp_timezone() );
	$moment = $nu->setTime( 0, 10 );
	if ( $moment <= $nu ) {
		$moment = $moment->modify( '+1 day' );
	}
	return $moment->getTimestamp();
}

add_action( 'init', 'mm_einddatum_inplannen' );
add_action( MM_EINDDATUM_CRON, 'mm_einddatum_uitvoeren' );
add_action( MM_EINDDATUM_VERVOLG, 'mm_einddatum_uitvoeren' );

/**
 * Met een datum staat er een dagelijkse controle; zonder datum niet.
 */
function mm_einddatum_inplannen() {
	if ( '' === mm_einddatum_datum() ) {
		if ( wp_next_scheduled( MM_EINDDATUM_CRON ) || wp_next_scheduled( MM_EINDDATUM_VERVOLG ) ) {
			wp_unschedule_hook( MM_EINDDATUM_CRON );
			wp_unschedule_hook( MM_EINDDATUM_VERVOLG );
		}
		return;
	}
	if ( ! wp_next_scheduled( MM_EINDDATUM_CRON ) ) {
		wp_schedule_event( mm_einddatum_volgende_nacht(), 'daily', MM_EINDDATUM_CRON );
	}
}


/* -------------------------------------------------------------------------
 * Inventaris
 * ---------------------------------------------------------------------- */

/**
 * De functies voor plugins en het bestandssysteem staan in wp-admin, en die
 * zijn in de cron niet geladen.
 */
function mm_einddatum_admin_includes() {
	if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'delete_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( ! function_exists( 'get_filesystem_method' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
}

/**
 * Per doel de pluginbestanden die op de site staan, met naam, versie en of
 * ze actief zijn. De toolkit zelf telt nooit mee.
 *
 * @return array[] Per doel: bestand => array( naam, versie, actief ).
 */
function mm_einddatum_plugins_zoeken() {

	mm_einddatum_admin_includes();

	// get_plugins() bewaart zijn uitkomst binnen het verzoek; na verwijderen
	// moet hij opnieuw kijken.
	wp_cache_delete( 'plugins', 'plugins' );
	$alle  = get_plugins();
	$eigen = plugin_basename( MM_TOOLKIT_BESTAND );

	$gevonden = array();
	foreach ( mm_einddatum_doelen() as $sleutel => $doel ) {
		$gevonden[ $sleutel ] = array();
		$namen                = array_map( 'strtolower', $doel['namen'] );
		foreach ( $alle as $bestand => $kop ) {
			if ( $bestand === $eigen ) {
				continue;
			}
			$naam = isset( $kop['Name'] ) ? trim( (string) $kop['Name'] ) : '';
			if ( in_array( strtolower( $naam ), $namen, true ) || in_array( $bestand, $doel['bestanden'], true ) ) {
				$gevonden[ $sleutel ][ $bestand ] = array(
					'naam'   => '' !== $naam ? $naam : $bestand,
					'versie' => isset( $kop['Version'] ) ? (string) $kop['Version'] : '',
					'actief' => is_plugin_active( $bestand ),
				);
			}
		}
	}

	return $gevonden;
}

/**
 * Heeft deze optie een inhoud?
 *
 * @param string $naam
 * @return bool
 */
function mm_einddatum_optie_gevuld( $naam ) {
	$waarde = get_option( $naam, null );
	return ! ( null === $waarde || false === $waarde || '' === $waarde || array() === $waarde );
}

/**
 * De geplande taken van WPvivid: alle hooks die met wpvivid beginnen.
 *
 * @return string[]
 */
function mm_einddatum_wpvivid_cron() {

	// De ID's van de schema's in Pro zijn zelf de cron-hook. Ze beginnen nu
	// met wpvivid, maar dat is niet beloofd; daarom ook op ID.
	$ids = array();
	foreach ( array( 'wpvivid_schedule_addon_setting', 'wpvivid_incremental_schedules' ) as $naam ) {
		$schemas = get_option( $naam, array() );
		foreach ( is_array( $schemas ) ? $schemas : array() as $schema ) {
			foreach ( array( 'id', 'files_schedule_id', 'db_schedule_id' ) as $sleutel ) {
				if ( is_array( $schema ) && ! empty( $schema[ $sleutel ] ) && is_string( $schema[ $sleutel ] ) ) {
					$ids[ $schema[ $sleutel ] ] = true;
				}
			}
		}
	}

	$hooks = array();
	$cron  = function_exists( '_get_cron_array' ) ? _get_cron_array() : array();
	foreach ( (array) $cron as $events ) {
		foreach ( (array) $events as $hook => $info ) {
			if ( 0 === strpos( (string) $hook, 'wpvivid' ) || isset( $ids[ $hook ] ) ) {
				$hooks[ $hook ] = true;
			}
		}
	}
	return array_keys( $hooks );
}

/**
 * De remotes in WPvivid, zonder sleutels.
 *
 * @return array[] Per remote: id, naam, type, map.
 */
function mm_einddatum_wpvivid_remotes() {
	$upload  = get_option( 'wpvivid_upload_setting', array() );
	$remotes = array();
	if ( ! is_array( $upload ) ) {
		return $remotes;
	}
	foreach ( $upload as $id => $remote ) {
		if ( ! is_array( $remote ) || empty( $remote['type'] ) ) {
			continue;
		}
		$remotes[] = array(
			'id'   => (string) $id,
			'naam' => ! empty( $remote['name'] ) ? (string) $remote['name'] : (string) $id,
			'type' => (string) $remote['type'],
			'map'  => ! empty( $remote['path'] ) && is_string( $remote['path'] ) ? $remote['path'] : '',
		);
	}
	return $remotes;
}

/**
 * Wat er van WPvivid op de site staat dat in stap 1 weg gaat.
 *
 * @return array
 */
function mm_einddatum_wpvivid_inventaris() {
	$historie = get_option( 'wpvivid_user_history', array() );
	$opties   = mm_einddatum_wpvivid_opties();
	$uit      = array(
		'remotes'  => mm_einddatum_wpvivid_remotes(),
		'gekozen'  => is_array( $historie ) && ! empty( $historie['remote_selected'] ),
		'schema'   => array(),
		'account'  => array(),
		'sleutels' => array(),
		'cron'     => mm_einddatum_wpvivid_cron(),
		'kopieen'  => mm_einddatum_wpvivid_kopieen( false ),
	);
	foreach ( $opties as $soort => $namen ) {
		foreach ( $namen as $naam ) {
			if ( mm_einddatum_optie_gevuld( $naam ) ) {
				$uit[ $soort ][] = $naam;
			}
		}
	}
	return $uit;
}

/**
 * Ziet dit eruit als de instellingen van een remote: een type en een sleutel,
 * token of wachtwoord? Zo worden ook kopieën gevonden waarvan de plek niet
 * bekend is.
 *
 * @param mixed $waarde
 * @return bool
 */
function mm_einddatum_is_remote( $waarde ) {
	if ( ! is_array( $waarde ) || empty( $waarde['type'] ) || ! is_string( $waarde['type'] ) ) {
		return false;
	}
	$geheim = array( 'access', 'secret', 'bucket', 'token', 'refresh_token', 'access_token', 'password', 'client_id', 'client_secret' );
	return (bool) array_intersect_key( $waarde, array_flip( $geheim ) );
}

/**
 * Haalt kopieën van remote-instellingen uit een waarde, op elke diepte: elke
 * sleutel remote_options (zo zet WPvivid ze in een taak) en elk array dat op
 * een remote lijkt (zo staan ze per back-up in de lijst onder 'remote').
 *
 * @param mixed $waarde
 * @param int   $aantal Telt het aantal weggehaalde kopieën op.
 * @return mixed
 */
function mm_einddatum_kopieen_eruit( $waarde, &$aantal ) {
	if ( ! is_array( $waarde ) ) {
		return $waarde;
	}
	foreach ( $waarde as $sleutel => $deel ) {
		if ( 'remote_options' === $sleutel && ! empty( $deel ) ) {
			$waarde[ $sleutel ] = false;
			$aantal++;
		} elseif ( mm_einddatum_is_remote( $deel ) ) {
			unset( $waarde[ $sleutel ] );
			$aantal++;
		} else {
			$waarde[ $sleutel ] = mm_einddatum_kopieen_eruit( $deel, $aantal );
		}
	}
	return $waarde;
}

/**
 * Kopieën van remote-instellingen buiten wpvivid_upload_setting. De gratis
 * plugin zet ze per back-up in wpvivid_backup_list en per taak in
 * wpvivid_task_list en wpvivid_exporter_task_list (nagelopen in 0.9.140).
 * Omdat Pro ook eigen lijsten kan hebben, kijkt dit in alle opties met
 * wpvivid_ ervoor en in de tabel wpvivid_options.
 *
 * @param bool $wissen Alleen kijken, of ook weghalen.
 * @return string[] Waar er kopieën stonden.
 */
function mm_einddatum_wpvivid_kopieen( $wissen ) {
	global $wpdb;

	$gevonden = array();
	$namen    = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wpvivid\\_%'" );
	foreach ( (array) $namen as $naam ) {
		if ( 'wpvivid_upload_setting' === $naam ) {
			continue;
		}
		$aantal = 0;
		$schoon = mm_einddatum_kopieen_eruit( get_option( $naam ), $aantal );
		if ( $aantal ) {
			$gevonden[] = $naam;
			if ( $wissen ) {
				update_option( $naam, $schoon, false );
			}
		}
	}

	$tabel = $wpdb->base_prefix . 'wpvivid_options';
	if ( $tabel === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $tabel ) ) ) ) {
		$rijen = $wpdb->get_results( "SELECT option_name, option_value FROM `{$tabel}`", ARRAY_A );
		foreach ( (array) $rijen as $rij ) {
			$aantal = 0;
			$schoon = mm_einddatum_kopieen_eruit( maybe_unserialize( $rij['option_value'] ), $aantal );
			if ( $aantal ) {
				$gevonden[] = 'wpvivid_options: ' . $rij['option_name'];
				if ( $wissen ) {
					$wpdb->update( $tabel, array( 'option_value' => maybe_serialize( $schoon ) ), array( 'option_name' => $rij['option_name'] ) );
				}
			}
		}
	}

	return $gevonden;
}

/**
 * Een remote als tekst, bijvoorbeeld "MediamoraS3 (Amazon S3, map klant_nl)".
 *
 * @param array $remote
 * @return string
 */
function mm_einddatum_remote_label( $remote ) {
	$soort = 'amazons3' === $remote['type'] ? 'Amazon S3' : $remote['type'];
	$map   = '' !== $remote['map'] ? ', map ' . $remote['map'] : '';
	return $remote['naam'] . ' (' . $soort . $map . ')';
}


/* -------------------------------------------------------------------------
 * Stap 1: WPvivid wissen
 * ---------------------------------------------------------------------- */

/**
 * Wist de remotes, de schema's, de koppeling met het Pro-account en de
 * overzetsleutels, en meldt alle geplande taken van WPvivid af. Rechtstreeks
 * in de opties, zodat het ook werkt als WPvivid niet (meer) actief is.
 *
 * @return array De inventaris van vóór het wissen: wat er gewist is.
 */
function mm_einddatum_wpvivid_wissen() {

	$voor = mm_einddatum_wpvivid_inventaris();

	// Remotes. Wat geen remote is, blijft staan.
	$upload = get_option( 'wpvivid_upload_setting', array() );
	if ( is_array( $upload ) && $voor['remotes'] ) {
		foreach ( $voor['remotes'] as $remote ) {
			unset( $upload[ $remote['id'] ] );
		}
		if ( $upload ) {
			update_option( 'wpvivid_upload_setting', $upload, false );
		} else {
			delete_option( 'wpvivid_upload_setting' );
		}
	}

	if ( $voor['gekozen'] ) {
		$historie                    = get_option( 'wpvivid_user_history', array() );
		$historie['remote_selected'] = array();
		update_option( 'wpvivid_user_history', $historie, false );
	}

	foreach ( array( 'schema', 'account', 'sleutels' ) as $soort ) {
		foreach ( $voor[ $soort ] as $naam ) {
			delete_option( $naam );
		}
	}

	foreach ( $voor['cron'] as $hook ) {
		wp_unschedule_hook( $hook );
	}

	if ( $voor['kopieen'] ) {
		mm_einddatum_wpvivid_kopieen( true );
	}

	return $voor;
}

/**
 * Alles van WPvivid weg: alle opties met wpvivid_ ervoor en de eigen tabel
 * wpvivid_options (WPvivid_Option, met de laatste back-upmelding). Alleen
 * als beide WPvivid-plugins weg zijn, anders zou het de instellingen van een
 * plugin onder zijn handen weghalen.
 *
 * @return int Het aantal opgeruimde opties.
 */
function mm_einddatum_wpvivid_opruimen() {
	global $wpdb;

	$namen = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wpvivid\\_%'" );
	$aantal = 0;
	foreach ( (array) $namen as $naam ) {
		if ( delete_option( $naam ) ) {
			$aantal++;
		}
	}

	$tabel = $wpdb->base_prefix . 'wpvivid_options';
	if ( $tabel === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $tabel ) ) ) ) {
		$wpdb->query( "DROP TABLE IF EXISTS `{$tabel}`" );
	}

	foreach ( mm_einddatum_wpvivid_cron() as $hook ) {
		wp_unschedule_hook( $hook );
	}

	return $aantal;
}

/**
 * Wat er na het wissen nog van de remote opslag, de schema's en het account
 * over is. Leeg: gelukt.
 *
 * @return string[]
 */
function mm_einddatum_wpvivid_rest() {
	$na   = mm_einddatum_wpvivid_inventaris();
	$rest = array();
	foreach ( $na['remotes'] as $remote ) {
		$rest[] = 'remote ' . mm_einddatum_remote_label( $remote );
	}
	if ( $na['gekozen'] ) {
		$rest[] = 'gekozen remote in wpvivid_user_history';
	}
	foreach ( array_merge( $na['schema'], $na['account'], $na['sleutels'] ) as $naam ) {
		$rest[] = 'optie ' . $naam;
	}
	foreach ( $na['cron'] as $hook ) {
		$rest[] = 'geplande taak ' . $hook;
	}
	foreach ( $na['kopieen'] as $plek ) {
		$rest[] = 'kopie van remote-instellingen in ' . $plek;
	}
	return $rest;
}


/* -------------------------------------------------------------------------
 * Uitvoeren
 * ---------------------------------------------------------------------- */

/**
 * De voortgang tot nu toe, aangevuld met lege waarden.
 *
 * @return array
 */
function mm_einddatum_stand() {
	$stand = get_option( MM_EINDDATUM_STAND, array() );
	$stand = is_array( $stand ) ? $stand : array();
	return array_merge(
		array(
			'gestart'    => 0,
			'plugins'    => array(),
			'wpvivid'    => array(
				'remotes' => array(),
				'schema'  => false,
				'account' => false,
				'cron'    => array(),
				'kopieen' => array(),
				'opties'  => 0,
			),
			'mail_hash'  => '',
			'mail_datum' => 0,
		),
		$stand
	);
}

/**
 * Wat er in deze ronde in WPvivid gewist is, bij wat er al gewist was.
 *
 * @param array $eerder
 * @param array $nu     Uitkomst van mm_einddatum_wpvivid_wissen().
 * @return array
 */
function mm_einddatum_wpvivid_samenvoegen( $eerder, $nu ) {
	foreach ( $nu['remotes'] as $remote ) {
		$eerder['remotes'][] = mm_einddatum_remote_label( $remote );
	}
	$eerder['remotes'] = array_values( array_unique( $eerder['remotes'] ) );
	$eerder['schema']  = $eerder['schema'] || (bool) $nu['schema'];
	$eerder['account'] = $eerder['account'] || (bool) $nu['account'];
	$eerder['cron']    = array_values( array_unique( array_merge( $eerder['cron'], $nu['cron'] ) ) );
	$eerder['kopieen'] = array_values( array_unique( array_merge( $eerder['kopieen'], $nu['kopieen'] ) ) );
	return $eerder;
}

/**
 * Kan WordPress hier zonder inloggegevens in de pluginmap schrijven?
 *
 * @return bool
 */
function mm_einddatum_direct() {
	mm_einddatum_admin_includes();
	return 'direct' === get_filesystem_method( array(), WP_PLUGIN_DIR );
}

/**
 * Is een plugin met deze status van de site?
 *
 * @param array  $stand
 * @param string $sleutel
 * @return bool
 */
function mm_einddatum_weg( $stand, $sleutel ) {
	$status = isset( $stand['plugins'][ $sleutel ]['status'] ) ? $stand['plugins'][ $sleutel ]['status'] : '';
	return in_array( $status, array( MM_EINDDATUM_VERWIJDERD, MM_EINDDATUM_NIET_GEVONDEN ), true );
}

/**
 * De cron-taak. Doet niets voor de einddatum, op multisite of als er al een
 * ronde loopt.
 */
function mm_einddatum_uitvoeren() {

	if ( is_multisite() ) {
		return;
	}

	$datum = mm_einddatum_datum();
	if ( ! mm_einddatum_bereikt( $datum ) ) {
		return;
	}

	if ( get_transient( MM_EINDDATUM_SLOT ) ) {
		return;
	}
	set_transient( MM_EINDDATUM_SLOT, time(), 10 * MINUTE_IN_SECONDS );

	mm_einddatum_admin_includes();

	$stand = mm_einddatum_stand();
	if ( ! $stand['gestart'] ) {
		$stand['gestart'] = time();
	}

	// 1. WPvivid wissen.
	$stand['wpvivid'] = mm_einddatum_wpvivid_samenvoegen( $stand['wpvivid'], mm_einddatum_wpvivid_wissen() );
	update_option( MM_EINDDATUM_STAND, $stand, false );

	// 2 en 3. Plugins, in de volgorde van mm_einddatum_doelen().
	$direct   = mm_einddatum_direct();
	$geladen  = false;
	$gevonden = mm_einddatum_plugins_zoeken();

	foreach ( mm_einddatum_doelen() as $sleutel => $doel ) {

		$bestanden = array_keys( $gevonden[ $sleutel ] );
		$vorige    = isset( $stand['plugins'][ $sleutel ]['status'] ) ? $stand['plugins'][ $sleutel ]['status'] : '';

		if ( ! $bestanden ) {
			// Er nooit geweest, of in een eerdere ronde weggehaald.
			$status = ( '' === $vorige || MM_EINDDATUM_NIET_GEVONDEN === $vorige ) ? MM_EINDDATUM_NIET_GEVONDEN : MM_EINDDATUM_VERWIJDERD;
			$stand['plugins'][ $sleutel ] = array( 'status' => $status, 'melding' => '' );
			continue;
		}

		$actief = array_values( array_filter( $bestanden, 'is_plugin_active' ) );
		if ( $actief ) {
			$geladen = true;
			deactivate_plugins( $actief );
		}

		if ( array_filter( $bestanden, 'is_plugin_active' ) ) {
			$stand['plugins'][ $sleutel ] = array( 'status' => MM_EINDDATUM_MISLUKT, 'melding' => 'deactiveren lukte niet' );
		} elseif ( ! $direct ) {
			$stand['plugins'][ $sleutel ] = array( 'status' => MM_EINDDATUM_GEDEACTIVEERD, 'melding' => '' );
		} else {
			$resultaat = delete_plugins( $bestanden );
			if ( true === $resultaat ) {
				$stand['plugins'][ $sleutel ] = array( 'status' => MM_EINDDATUM_VERWIJDERD, 'melding' => '' );
			} else {
				$stand['plugins'][ $sleutel ] = array(
					'status'  => MM_EINDDATUM_MISLUKT,
					'melding' => is_wp_error( $resultaat ) ? $resultaat->get_error_message() : 'verwijderen lukte niet',
				);
			}
		}

		update_option( MM_EINDDATUM_STAND, $stand, false );
	}

	update_option( MM_EINDDATUM_STAND, $stand, false );

	// Er was in dit verzoek plugincode geladen: verder in een schoon verzoek.
	if ( $geladen ) {
		if ( ! wp_next_scheduled( MM_EINDDATUM_VERVOLG ) ) {
			wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, MM_EINDDATUM_VERVOLG );
		}
		delete_transient( MM_EINDDATUM_SLOT );
		return;
	}

	// Beide WPvivid-plugins weg: de rest van WPvivid ook.
	if ( mm_einddatum_weg( $stand, 'wpvivid_pro' ) && mm_einddatum_weg( $stand, 'wpvivid' ) ) {
		$stand['wpvivid']['opties'] += mm_einddatum_wpvivid_opruimen();
	}

	$rest  = mm_einddatum_wpvivid_rest();
	$klaar = $direct && ! $rest;
	foreach ( array_keys( mm_einddatum_doelen() ) as $sleutel ) {
		$klaar = $klaar && mm_einddatum_weg( $stand, $sleutel );
	}

	// 4. Mail. Dezelfde uitkomst gaat maar één keer.
	$mail = mm_einddatum_mail_tekst( $datum, $stand, $rest, $direct, $klaar );
	$hash = md5( $mail['onderwerp'] . "\n" . $mail['tekst'] );
	if ( $hash !== $stand['mail_hash'] && mm_einddatum_mail_versturen( $mail ) ) {
		$stand['mail_hash']  = $hash;
		$stand['mail_datum'] = time();
	}
	update_option( MM_EINDDATUM_STAND, $stand, false );

	// 5. De toolkit zelf, pas als de eindstand gemaild is.
	if ( $klaar && $hash === $stand['mail_hash'] ) {
		mm_einddatum_zelf_verwijderen();
		return;
	}

	delete_transient( MM_EINDDATUM_SLOT );
}

/**
 * Deactiveren met de gewone haak, zodat mm_toolkit_deactiveren() de events en
 * de blokken in .htaccess opruimt. Daarna verwijderen; dat draait
 * uninstall.php, die de opties van de toolkit opruimt, ook die van deze module.
 */
function mm_einddatum_zelf_verwijderen() {
	$eigen = plugin_basename( MM_TOOLKIT_BESTAND );
	deactivate_plugins( array( $eigen ) );
	delete_plugins( array( $eigen ) );
}


/* -------------------------------------------------------------------------
 * Mail
 * ---------------------------------------------------------------------- */

/**
 * Het domein van deze site, zonder www.
 *
 * @return string
 */
function mm_einddatum_domein() {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	return preg_replace( '/^www\./i', '', is_string( $host ) ? $host : 'onbekend' );
}

/**
 * Onderwerp en tekst van de mail. Zonder tijdstip, zodat dezelfde uitkomst
 * dezelfde tekst geeft en niet elke nacht opnieuw gemaild wordt.
 *
 * @param string   $datum
 * @param array    $stand
 * @param string[] $rest   Wat er van WPvivid over is.
 * @param bool     $direct
 * @param bool     $klaar
 * @return string[] onderwerp, tekst.
 */
function mm_einddatum_mail_tekst( $datum, $stand, $rest, $direct, $klaar ) {

	$domein = mm_einddatum_domein();
	$regels = array();

	$regels[] = 'Site: ' . $domein;
	$regels[] = 'Einddatum onderhoud: ' . mm_einddatum_leesbaar( $datum );
	$regels[] = '';
	$regels[] = 'Plugins:';

	$gedeactiveerd = false;
	foreach ( mm_einddatum_doelen() as $sleutel => $doel ) {
		$p      = isset( $stand['plugins'][ $sleutel ] ) ? $stand['plugins'][ $sleutel ] : array( 'status' => '', 'melding' => '' );
		$status = $p['status'];
		if ( MM_EINDDATUM_GEDEACTIVEERD === $status ) {
			$gedeactiveerd = true;
			$status        = 'alleen gedeactiveerd, niet verwijderd';
		} elseif ( MM_EINDDATUM_MISLUKT === $status ) {
			$status = 'mislukt' . ( '' !== $p['melding'] ? ': ' . $p['melding'] : '' );
		}
		$regels[] = '- ' . $doel['naam'] . ': ' . $status;
	}

	$regels[] = '';
	$w        = $stand['wpvivid'];
	$regels[] = 'Wissen in WPvivid: ' . ( $rest ? 'NIET gelukt' : 'gelukt' );
	if ( $w['remotes'] ) {
		$regels[] = '- Remote opslag gewist: ' . implode( ', ', $w['remotes'] );
	}
	if ( $w['schema'] ) {
		$regels[] = '- Back-upschema\'s gewist';
	}
	if ( $w['account'] ) {
		$regels[] = '- Koppeling met het WPvivid Pro-account gewist';
	}
	if ( $w['kopieen'] ) {
		$regels[] = '- Kopieën van de remote-instellingen gewist uit: ' . implode( ', ', $w['kopieen'] );
	}
	if ( $w['cron'] ) {
		$regels[] = '- Geplande taken afgemeld: ' . implode( ', ', $w['cron'] );
	}
	if ( $w['opties'] ) {
		$regels[] = '- Overige gegevens van WPvivid opgeruimd: ' . $w['opties'] . ' opties';
	}
	if ( ! $w['remotes'] && ! $w['schema'] && ! $w['account'] && ! $w['cron'] && ! $w['kopieen'] && ! $w['opties'] && ! $rest ) {
		$regels[] = '- Er stond niets van WPvivid op de site';
	}
	foreach ( $rest as $r ) {
		$regels[] = '- Nog aanwezig: ' . $r;
	}

	$regels[] = '';
	if ( $klaar ) {
		$regels[] = 'De Mediamora Toolkit verwijdert zichzelf direct na deze mail.';
	} else {
		if ( ! $direct || $gedeactiveerd ) {
			$regels[] = 'WordPress kan in de cron niet zelf in de pluginmap schrijven (geen \'direct\'-methode in WP_Filesystem), dus de plugins zijn alleen gedeactiveerd. Verwijder ze zelf.';
		}
		$regels[] = 'De Mediamora Toolkit blijft op de site staan en probeert het elke nacht opnieuw. Verandert de uitkomst, dan volgt een nieuwe mail.';
	}

	$regels[] = '';
	$regels[] = 'Haal de site uit het MainWP-dashboard en zet het klantrecord in de Projecten-tool op pakket geen.';

	return array(
		'onderwerp' => '[Einddatum onderhoud] ' . $domein,
		'tekst'     => implode( "\n", $regels ),
	);
}

/**
 * Eerst via wp_mail(), zoals elke mail van de site. Lukt dat niet, dan via de
 * mailserver van de hosting zelf, zoals de Formuliermonitor.
 *
 * @param string[] $mail
 * @return bool
 */
function mm_einddatum_mail_versturen( $mail ) {

	$domein = mm_einddatum_domein();
	$kop    = array(
		'Content-Type: text/plain; charset=UTF-8',
		'X-Mediamora-Einddatum: 1',
		'X-Mediamora-Site: ' . $domein,
	);

	if ( wp_mail( MM_MONITOR_ONTVANGER, $mail['onderwerp'], $mail['tekst'], $kop ) ) {
		return true;
	}

	if ( ! function_exists( 'mail' ) ) {
		return false;
	}

	$afzender = 'no-reply@' . $domein;
	array_unshift( $kop, 'From: Mediamora Toolkit ' . $domein . ' <' . $afzender . '>' );

	return @mail( MM_MONITOR_ONTVANGER, $mail['onderwerp'], $mail['tekst'], implode( "\r\n", $kop ), '-f' . $afzender );
}


/* -------------------------------------------------------------------------
 * Instellingen
 * ---------------------------------------------------------------------- */

/**
 * Regel onder de module in de tabel.
 *
 * @return string HTML.
 */
function mm_einddatum_status_regel() {
	$datum = mm_einddatum_datum();
	if ( '' === $datum ) {
		return 'Geen einddatum gepland.';
	}
	$tekst = 'Einddatum: ' . esc_html( mm_einddatum_leesbaar( $datum ) );
	if ( mm_einddatum_bereikt( $datum ) ) {
		$tekst .= ', <span style="color:#b32d2e;">bereikt, wordt uitgevoerd</span>';
	}
	return $tekst . '.';
}

/**
 * Mag de ingelogde gebruiker hier iets doen?
 *
 * @return bool
 */
function mm_einddatum_mag() {
	return current_user_can( 'manage_options' ) && mm_toolkit_mediamora_gebruiker();
}

/**
 * Terug naar het blok op het tabblad Mediamora.
 *
 * @param array $args
 */
function mm_einddatum_terug( $args ) {
	wp_safe_redirect( add_query_arg( $args, mm_toolkit_scherm_url( 'mediamora' ) ) . '#mm-einddatum' );
	exit;
}

add_action( 'admin_post_mm_einddatum_opslaan', 'mm_einddatum_opslaan' );

/**
 * Leeg: annuleren. Een datum van vandaag of eerder alleen met het vinkje,
 * zodat een tikfout niet meteen alles wist.
 */
function mm_einddatum_opslaan() {

	if ( ! mm_einddatum_mag() ) {
		wp_die( 'Geen toegang.', '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'mm_einddatum_opslaan' );

	$datum = isset( $_POST['mm_einddatum'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['mm_einddatum'] ) ) ) : '';

	if ( '' === $datum ) {
		delete_option( MM_EINDDATUM_OPTIE );
		delete_option( MM_EINDDATUM_STAND );
		wp_unschedule_hook( MM_EINDDATUM_CRON );
		wp_unschedule_hook( MM_EINDDATUM_VERVOLG );
		mm_einddatum_terug( array( 'mm_einddatum' => 'geannuleerd' ) );
	}

	if ( ! mm_einddatum_geldig( $datum ) ) {
		mm_einddatum_terug( array( 'mm_einddatum' => 'ongeldig' ) );
	}

	$nu = mm_einddatum_bereikt( $datum );
	if ( $nu && empty( $_POST['mm_einddatum_bevestig'] ) ) {
		mm_einddatum_terug(
			array(
				'mm_einddatum'         => 'bevestigen',
				'mm_einddatum_voorstel' => $datum,
			)
		);
	}

	update_option( MM_EINDDATUM_OPTIE, $datum, true );
	mm_einddatum_inplannen();

	// Vandaag of eerder: niet tot vannacht wachten.
	if ( $nu && ! wp_next_scheduled( MM_EINDDATUM_VERVOLG ) ) {
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, MM_EINDDATUM_VERVOLG );
	}

	mm_einddatum_terug( array( 'mm_einddatum' => $nu ? 'gestart' : 'opgeslagen' ) );
}

add_action( 'admin_post_mm_einddatum_proefdraai', 'mm_einddatum_proefdraai_knop' );

function mm_einddatum_proefdraai_knop() {
	if ( ! mm_einddatum_mag() ) {
		wp_die( 'Geen toegang.', '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'mm_einddatum_proefdraai' );
	set_transient( MM_EINDDATUM_PROEF . get_current_user_id(), mm_einddatum_proefdraai(), 10 * MINUTE_IN_SECONDS );
	mm_einddatum_terug( array( 'mm_einddatum' => 'proef' ) );
}

/**
 * Alles nalopen wat de cron-taak zou doen, zonder iets te wijzigen.
 *
 * @return array Tijdstip, en regels met label, tekst en of het een
 *               probleem is.
 */
function mm_einddatum_proefdraai() {

	$regels = array();
	$regel  = function ( $label, $tekst, $probleem = false ) use ( &$regels ) {
		$regels[] = array( $label, $tekst, $probleem );
	};

	$datum = mm_einddatum_datum();
	if ( '' === $datum ) {
		$regel( 'Einddatum', 'Geen einddatum ingesteld: er gebeurt niets.' );
	} elseif ( mm_einddatum_bereikt( $datum ) ) {
		$regel( 'Einddatum', mm_einddatum_leesbaar( $datum ) . ', bereikt: de volgende cron-ronde voert alles uit.' );
	} else {
		$regel( 'Einddatum', mm_einddatum_leesbaar( $datum ) . '. Vandaag is het hier ' . mm_einddatum_leesbaar( mm_einddatum_vandaag() ) . ' (' . wp_timezone_string() . ').' );
	}

	$volgende = wp_next_scheduled( MM_EINDDATUM_CRON );
	$vervolg  = wp_next_scheduled( MM_EINDDATUM_VERVOLG );
	if ( '' !== $datum ) {
		$tekst = $volgende ? 'Dagelijks, de volgende keer ' . wp_date( 'j F Y H:i', $volgende ) . '.' : 'Niet ingepland.';
		if ( $vervolg ) {
			$tekst .= ' Vervolgronde om ' . wp_date( 'H:i', $vervolg ) . '.';
		}
		$regel( 'Controle', $tekst, ! $volgende );
	}
	if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
		$regel( 'WP-cron', 'DISABLE_WP_CRON staat aan: er moet een echte cron op de server wp-cron.php aanroepen, anders gebeurt er niets.', true );
	}

	if ( is_multisite() ) {
		$regel( 'Multisite', 'Niet ondersteund: de module doet op een multisite niets.', true );
	}

	if ( get_transient( MM_EINDDATUM_SLOT ) ) {
		$regel( 'Slot', 'Er loopt op dit moment een ronde.' );
	}

	$direct = mm_einddatum_direct();
	foreach ( mm_einddatum_plugins_zoeken() as $sleutel => $bestanden ) {
		$doel = mm_einddatum_doelen()[ $sleutel ];
		if ( ! $bestanden ) {
			$regel( $doel['naam'], 'Niet gevonden.' );
			continue;
		}
		foreach ( $bestanden as $bestand => $p ) {
			$regel(
				$doel['naam'],
				sprintf(
					'Gevonden: %s %s (%s), %s. Wordt gedeactiveerd%s.',
					$p['naam'],
					$p['versie'],
					$bestand,
					$p['actief'] ? 'actief' : 'niet actief',
					$direct ? ' en verwijderd' : ', niet verwijderd'
				)
			);
		}
	}

	foreach ( mm_einddatum_wpvivid_overzicht() as $tekst ) {
		$regel( 'WPvivid', $tekst );
	}

	$methode = get_filesystem_method( array(), WP_PLUGIN_DIR );
	if ( $direct ) {
		$regel( 'Bestandssysteem', 'Methode direct: de plugins en daarna de toolkit kunnen verwijderd worden.' );
	} else {
		$regel( 'Bestandssysteem', 'Methode ' . $methode . ', niet direct: de plugins worden alleen gedeactiveerd en de toolkit blijft staan.', true );
	}

	$regel( 'Mail', 'Gaat naar ' . MM_MONITOR_ONTVANGER . ', via wp_mail() en anders via mail() van de hosting.' );
	$regel( 'Toolkit', $direct ? 'Wordt na de mail gedeactiveerd en verwijderd; uninstall.php ruimt de instellingen op.' : 'Blijft staan.' );

	$stand = mm_einddatum_stand();
	if ( $stand['gestart'] ) {
		$tekst = 'Eerder gestart op ' . wp_date( 'j F Y H:i', $stand['gestart'] ) . '.';
		if ( $stand['mail_datum'] ) {
			$tekst .= ' Laatste mail ' . wp_date( 'j F Y H:i', $stand['mail_datum'] ) . '.';
		}
		$regel( 'Voortgang', $tekst );
	}

	return array(
		'tijd'   => time(),
		'regels' => $regels,
	);
}

/**
 * Wat er in WPvivid gewist wordt, in gewone zinnen.
 *
 * @return string[]
 */
function mm_einddatum_wpvivid_overzicht() {
	$inv    = mm_einddatum_wpvivid_inventaris();
	$regels = array();
	foreach ( $inv['remotes'] as $remote ) {
		$regels[] = 'Remote opslag ' . mm_einddatum_remote_label( $remote ) . ' wordt gewist.';
	}
	if ( $inv['gekozen'] ) {
		$regels[] = 'De keuze voor de remote opslag wordt leeggemaakt.';
	}
	if ( $inv['schema'] ) {
		$regels[] = 'Back-upschema\'s worden gewist (' . implode( ', ', $inv['schema'] ) . ').';
	}
	if ( $inv['account'] ) {
		$regels[] = 'De koppeling met het WPvivid Pro-account wordt gewist (' . implode( ', ', $inv['account'] ) . ').';
	}
	if ( $inv['sleutels'] ) {
		$regels[] = 'De sleutels om de site over te zetten worden gewist (' . implode( ', ', $inv['sleutels'] ) . ').';
	}
	if ( $inv['kopieen'] ) {
		$regels[] = 'Kopieën van de remote-instellingen worden gewist uit: ' . implode( ', ', $inv['kopieen'] ) . '.';
	}
	if ( $inv['cron'] ) {
		$regels[] = 'Geplande taken worden afgemeld: ' . implode( ', ', $inv['cron'] ) . '.';
	}
	$regels[] = $regels ? 'Zijn beide WPvivid-plugins weg, dan gaan ook de overige opties met wpvivid_ ervoor en de tabel wpvivid_options weg.' : 'Er staat niets van WPvivid in de database.';
	return $regels;
}

/**
 * Het blok onder de modules op het tabblad Mediamora.
 */
function mm_einddatum_formulier() {

	if ( ! mm_einddatum_mag() ) {
		return;
	}

	$melding  = isset( $_GET['mm_einddatum'] ) ? sanitize_key( wp_unslash( $_GET['mm_einddatum'] ) ) : '';
	$voorstel = isset( $_GET['mm_einddatum_voorstel'] ) ? sanitize_text_field( wp_unslash( $_GET['mm_einddatum_voorstel'] ) ) : '';
	$datum    = mm_einddatum_datum();
	$waarde   = ( 'bevestigen' === $melding && mm_einddatum_geldig( $voorstel ) ) ? $voorstel : $datum;

	echo '<h2 id="mm-einddatum" style="margin-top:32px;">Einddatum onderhoud</h2>';

	$meldingen = array(
		'opgeslagen'  => array( 'success', 'Einddatum opgeslagen.' ),
		'gestart'     => array( 'warning', 'Einddatum opgeslagen. De datum is bereikt: de eerste ronde start binnen enkele minuten, zodra er een bezoek op de site is.' ),
		'geannuleerd' => array( 'success', 'Einddatum gewist. Er is niets gepland.' ),
		'ongeldig'    => array( 'error', 'Dat is geen geldige datum. Er is niets veranderd.' ),
		'bevestigen'  => array( 'error', 'Deze datum is vandaag of al voorbij. Dan begint het verwijderen meteen. Vink het hokje hieronder aan om dat te bevestigen. Er is nog niets opgeslagen.' ),
	);
	if ( isset( $meldingen[ $melding ] ) ) {
		echo '<div class="notice notice-' . esc_attr( $meldingen[ $melding ][0] ) . ' inline"><p>' . esc_html( $meldingen[ $melding ][1] ) . '</p></div>';
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="mm_einddatum_opslaan">';
	wp_nonce_field( 'mm_einddatum_opslaan' );
	echo '<table class="form-table" role="presentation" style="max-width:900px;"><tr><th scope="row"><label for="mm-einddatum-veld">Einddatum onderhoud</label></th><td>';
	echo '<input type="date" id="mm-einddatum-veld" name="mm_einddatum" value="' . esc_attr( $waarde ) . '">';
	if ( 'bevestigen' === $melding ) {
		echo '<p><label style="color:#b32d2e;"><input type="checkbox" name="mm_einddatum_bevestig" value="1"> Ja, voer het afsluiten nu uit: WPvivid wissen en de plugins en de toolkit verwijderen</label></p>';
	}
	echo '<p class="description">Leeg betekent niets gepland; leegmaken en opslaan annuleert. Op deze datum (tijdzone van de site, nu ' . esc_html( wp_timezone_string() ) . ') voert de dagelijkse cron-taak alles uit, ook als er niemand meer inlogt.</p>';
	echo '</td></tr></table>';
	submit_button( 'Einddatum opslaan', 'primary', 'submit', false );
	echo '</form>';

	// Wat er op die datum gebeurt.
	echo '<h3>Wat er op de einddatum gebeurt</h3>';
	echo '<ol style="max-width:900px;">';
	echo '<li><strong>WPvivid wissen.</strong><ul style="list-style:disc;margin-left:20px;">';
	foreach ( mm_einddatum_wpvivid_overzicht() as $tekst ) {
		echo '<li>' . esc_html( $tekst ) . '</li>';
	}
	echo '</ul></li>';
	echo '<li><strong>Plugins deactiveren en verwijderen.</strong><ul style="list-style:disc;margin-left:20px;">';
	foreach ( mm_einddatum_plugins_zoeken() as $sleutel => $bestanden ) {
		$naam = mm_einddatum_doelen()[ $sleutel ]['naam'];
		if ( ! $bestanden ) {
			echo '<li>' . esc_html( $naam ) . ': niet gevonden</li>';
			continue;
		}
		foreach ( $bestanden as $bestand => $p ) {
			echo '<li>' . esc_html( $naam ) . ': gevonden, <code>' . esc_html( $bestand ) . '</code> ' . esc_html( $p['versie'] ) . ( $p['actief'] ? '' : ' (niet actief)' ) . '</li>';
		}
	}
	echo '</ul></li>';
	echo '<li><strong>Mail naar Mediamora</strong> met het resultaat.</li>';
	echo '<li><strong>De toolkit verwijdert zichzelf.</strong></li>';
	echo '</ol>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="mm_einddatum_proefdraai">';
	wp_nonce_field( 'mm_einddatum_proefdraai' );
	submit_button( 'Proefdraai', 'secondary', 'submit', false );
	echo ' <span class="description">Controleert alles en laat zien wat er zou gebeuren, zonder iets te wijzigen.</span>';
	echo '</form>';

	$proef = get_transient( MM_EINDDATUM_PROEF . get_current_user_id() );
	if ( 'proef' === $melding && is_array( $proef ) && ! empty( $proef['regels'] ) ) {
		echo '<h3>Proefdraai ' . esc_html( wp_date( 'j F Y H:i', $proef['tijd'] ) ) . '</h3>';
		echo '<p>Er is niets gewijzigd.</p>';
		echo '<table class="widefat striped" style="max-width:900px;"><tbody>';
		foreach ( $proef['regels'] as $r ) {
			echo '<tr><th style="width:200px;">' . esc_html( $r[0] ) . '</th><td' . ( $r[2] ? ' style="color:#b32d2e;"' : '' ) . '>' . esc_html( $r[1] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
}
