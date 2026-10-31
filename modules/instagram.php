<?php
/**
 * Mediamora Toolkit, module: Instagram-feed
 * Wordt alleen geladen als de module aanstaat.
 *
 * Toont de laatste Instagram-posts van de klant via een shortcode, met de
 * gegevens uit een JSON-feed van Behold (behold.so). De klant koppelt haar
 * Instagram-account zelf via de koppellink van Behold; in de module komt
 * alleen de feed-URL.
 *
 * Waarom niet de widget van Behold: die laadt bij elke bezoeker een script
 * en de foto's van Behold, en elke paginaweergave telt dan als view. Het
 * gratis plan heeft 1.200 views per maand en pauzeert het account als die
 * op zijn. In de footer is dat zo bereikt. Deze module haalt de feed twee
 * keer per dag op de server op, zet de foto's in uploads/mediamora-instagram
 * en toont ze vanaf de eigen site. Bij Behold komen dan alleen die
 * ophaalacties binnen, zo'n 60 per maand, hoe druk de site ook is. Bezoekers
 * laden niets van Behold of Instagram, dus geen externe scripts en geen
 * cookies.
 *
 * Gebruik: [mm_instagram] in een Shortcode-widget van Elementor, bijvoorbeeld
 * in de footer. Opties: aantal, kolommen, kolommen_tablet, kolommen_mobiel,
 * ruimte (px), afronding (px) en verhouding (auto of bijvoorbeeld 1/1). Kleuren en titel komen uit Elementor zelf.
 *
 * Cache: de shortcode zet de LiteSpeed-tag mm_instagram op de pagina. Is de
 * feed veranderd, dan purget de module alleen die tag. Foto's van posts die
 * uit de feed vallen blijven nog 14 dagen staan, zodat een pagina uit een
 * andere cache geen kapotte afbeelding toont.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MM_INSTAGRAM_INSTELLINGEN = 'mm_instagram_instellingen';
const MM_INSTAGRAM_FEED         = 'mm_instagram_feed';
const MM_INSTAGRAM_STATUS       = 'mm_instagram_status';
const MM_INSTAGRAM_SLOT         = 'mm_instagram_slot';
const MM_INSTAGRAM_MAP          = 'mediamora-instagram';
const MM_INSTAGRAM_MAX          = 12;   // meer posts dan dit nooit
const MM_INSTAGRAM_BEWAAR       = 14;   // dagen dat oude foto's blijven staan


/* -------------------------------------------------------------------------
 * Instellingen en opslag
 * ---------------------------------------------------------------------- */

function mm_instagram_instellingen() {
	$opgeslagen = get_option( MM_INSTAGRAM_INSTELLINGEN, array() );
	$opgeslagen = is_array( $opgeslagen ) ? $opgeslagen : array();
	return array(
		'url'    => isset( $opgeslagen['url'] ) ? (string) $opgeslagen['url'] : '',
		'aantal' => isset( $opgeslagen['aantal'] ) ? max( 1, min( MM_INSTAGRAM_MAX, (int) $opgeslagen['aantal'] ) ) : 6,
	);
}

function mm_instagram_feed() {
	$feed = get_option( MM_INSTAGRAM_FEED, array() );
	return ( is_array( $feed ) && isset( $feed['posts'] ) && is_array( $feed['posts'] ) ) ? $feed : array( 'posts' => array(), 'opgehaald' => 0 );
}

function mm_instagram_status() {
	$status = get_option( MM_INSTAGRAM_STATUS, array() );
	$status = is_array( $status ) ? $status : array();
	return wp_parse_args(
		$status,
		array(
			'poging' => 0,       // laatste ophaalpoging
			'fout'   => '',      // foutmelding van die poging, leeg als het goed ging
			'teller' => array(), // ophaalacties per maand (jjjj-mm), dus views bij Behold
			'oud'    => array(), // bestandsnaam => tijdstip waarna hij weg mag
		)
	);
}

/**
 * Alleen een JSON-feed van Behold, over https.
 */
function mm_instagram_url_geldig( $url ) {
	$delen = wp_parse_url( $url );
	return is_array( $delen )
		&& isset( $delen['scheme'], $delen['host'], $delen['path'] )
		&& 'https' === $delen['scheme']
		&& 'feeds.behold.so' === strtolower( $delen['host'] )
		&& (bool) preg_match( '#^/[A-Za-z0-9_-]+/?$#', $delen['path'] );
}

/**
 * Map en URL van de foto's in uploads.
 */
function mm_instagram_map() {
	$uploads = wp_upload_dir( null, false );
	return array(
		'pad' => trailingslashit( $uploads['basedir'] ) . MM_INSTAGRAM_MAP,
		'url' => trailingslashit( $uploads['baseurl'] ) . MM_INSTAGRAM_MAP,
	);
}


/* -------------------------------------------------------------------------
 * Inplannen
 *
 * Twee keer per dag ophalen. De feed van Behold ververst op het gratis plan
 * één keer per dag, dus vaker heeft geen zin en kost alleen views. Per site
 * een vaste verschuiving, zodat niet alle sites tegelijk ophalen. Zonder
 * feed-URL staat er geen event. Uitzetten of deactiveren haalt het event weg,
 * zie mediamora-toolkit.php.
 * ---------------------------------------------------------------------- */

add_action( 'init', 'mm_instagram_inplannen' );

function mm_instagram_inplannen() {
	$heeft_url = '' !== mm_instagram_instellingen()['url'];
	$gepland   = wp_next_scheduled( 'mm_instagram_ophalen' );

	if ( $heeft_url && ! $gepland ) {
		wp_schedule_event( time() + 300 + ( absint( crc32( home_url() ) ) % 3600 ), 'twicedaily', 'mm_instagram_ophalen' );
	} elseif ( ! $heeft_url && $gepland ) {
		wp_unschedule_hook( 'mm_instagram_ophalen' );
	}
}

add_action( 'mm_instagram_ophalen', 'mm_instagram_ophalen' );


/* -------------------------------------------------------------------------
 * Ophalen
 * ---------------------------------------------------------------------- */

/**
 * Haalt de feed op, zet de foto's klaar en bewaart de posts.
 *
 * Gaat er iets mis, dan blijft de vorige feed staan en komt de fout in de
 * status. Lukt één foto niet, dan valt alleen die post weg.
 *
 * @return true|WP_Error
 */
function mm_instagram_ophalen() {

	$instellingen = mm_instagram_instellingen();
	if ( '' === $instellingen['url'] ) {
		return new WP_Error( 'mm_instagram_geen_url', 'Er is nog geen feed-URL ingevuld.' );
	}

	// Slot: cron en de knop Nu ophalen niet tegelijk.
	if ( get_transient( MM_INSTAGRAM_SLOT ) ) {
		return new WP_Error( 'mm_instagram_bezig', 'Er wordt al opgehaald, probeer het over een minuut opnieuw.' );
	}
	set_transient( MM_INSTAGRAM_SLOT, 1, 2 * MINUTE_IN_SECONDS );

	$uitkomst = mm_instagram_ophalen_uitvoeren( $instellingen );

	delete_transient( MM_INSTAGRAM_SLOT );

	update_option(
		MM_INSTAGRAM_STATUS,
		array_merge(
			mm_instagram_status(),
			array(
				'poging' => time(),
				'fout'   => is_wp_error( $uitkomst ) ? $uitkomst->get_error_message() : '',
			)
		),
		false
	);

	return $uitkomst;
}

/**
 * @param array $instellingen Uit mm_instagram_instellingen().
 * @return true|WP_Error
 */
function mm_instagram_ophalen_uitvoeren( $instellingen ) {

	if ( ! mm_instagram_url_geldig( $instellingen['url'] ) ) {
		return new WP_Error( 'mm_instagram_url', 'De feed-URL is geen JSON-feed van Behold (https://feeds.behold.so/...).' );
	}

	$antwoord = wp_remote_get(
		$instellingen['url'],
		array(
			'timeout' => 15,
			'headers' => array( 'Accept' => 'application/json' ),
		)
	);

	// Elke aanvraag die Behold bereikt telt daar als view.
	mm_instagram_tel_aanvraag();

	if ( is_wp_error( $antwoord ) ) {
		return new WP_Error( 'mm_instagram_verbinding', 'Behold was niet bereikbaar: ' . $antwoord->get_error_message() );
	}

	$code = (int) wp_remote_retrieve_response_code( $antwoord );
	if ( 200 !== $code ) {
		return new WP_Error( 'mm_instagram_http', 'Behold gaf code ' . $code . '. Staat de feed nog aan, of is het account gepauzeerd?' );
	}

	$data = json_decode( wp_remote_retrieve_body( $antwoord ), true );
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'mm_instagram_json', 'Het antwoord van Behold was geen geldige JSON.' );
	}

	// Nieuwe feeds geven een object met posts, oude een lijst met posts.
	$ruw = isset( $data['posts'] ) && is_array( $data['posts'] ) ? $data['posts'] : ( isset( $data[0] ) ? $data : array() );
	if ( empty( $ruw ) ) {
		return new WP_Error( 'mm_instagram_leeg', 'De feed bevat geen posts.' );
	}

	$map = mm_instagram_map();
	if ( ! wp_mkdir_p( $map['pad'] ) ) {
		return new WP_Error( 'mm_instagram_map', 'Kon de map ' . MM_INSTAGRAM_MAP . ' in uploads niet aanmaken.' );
	}
	if ( ! file_exists( $map['pad'] . '/index.php' ) ) {
		@file_put_contents( $map['pad'] . '/index.php', "<?php\n// Stilte.\n" ); // phpcs:ignore
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';

	$oud_feed = mm_instagram_feed();
	$oud      = array();
	foreach ( $oud_feed['posts'] as $post ) {
		$oud[ $post['id'] ] = $post;
	}

	$posts = array();
	foreach ( $ruw as $item ) {
		if ( count( $posts ) >= $instellingen['aantal'] ) {
			break;
		}
		if ( ! is_array( $item ) || ( isset( $item['visibility'] ) && 'visible' !== $item['visibility'] ) ) {
			continue;
		}
		$post = mm_instagram_post_klaarzetten( $item, $map['pad'], $oud );
		if ( $post ) {
			$posts[] = $post;
		}
	}

	if ( empty( $posts ) ) {
		return new WP_Error( 'mm_instagram_fotos', 'Er kwamen posts binnen, maar geen enkele foto kon worden opgehaald.' );
	}

	// Foto's die niet meer in de feed staan nog even laten staan, daarna weg.
	$status = mm_instagram_status();
	$nu     = array();
	foreach ( $posts as $post ) {
		foreach ( $post['fotos'] as $foto ) {
			$nu[ $foto['bestand'] ] = true;
		}
	}
	foreach ( $oud as $post ) {
		foreach ( $post['fotos'] as $foto ) {
			if ( ! isset( $nu[ $foto['bestand'] ] ) && ! isset( $status['oud'][ $foto['bestand'] ] ) ) {
				$status['oud'][ $foto['bestand'] ] = time() + MM_INSTAGRAM_BEWAAR * DAY_IN_SECONDS;
			}
		}
	}
	foreach ( $status['oud'] as $bestand => $weg_na ) {
		if ( isset( $nu[ $bestand ] ) ) {
			unset( $status['oud'][ $bestand ] );
		} elseif ( $weg_na < time() ) {
			@unlink( $map['pad'] . '/' . basename( $bestand ) ); // phpcs:ignore
			unset( $status['oud'][ $bestand ] );
		}
	}
	update_option( MM_INSTAGRAM_STATUS, array_merge( mm_instagram_status(), array( 'oud' => $status['oud'] ) ), false );

	$veranderd = wp_json_encode( $posts ) !== wp_json_encode( $oud_feed['posts'] );

	update_option(
		MM_INSTAGRAM_FEED,
		array(
			'posts'     => $posts,
			'opgehaald' => time(),
		),
		true // op elke pagina nodig, en klein
	);

	if ( $veranderd ) {
		do_action( 'litespeed_purge', 'mm_instagram' );
	}

	return true;
}

/**
 * Eén post uit de feed klaarzetten: foto's downloaden (of hergebruiken) en
 * de gegevens die de shortcode nodig heeft.
 *
 * @param array  $item Een post uit de JSON van Behold.
 * @param string $pad  Map in uploads.
 * @param array  $oud  Posts van de vorige keer, op id.
 * @return array|null
 */
function mm_instagram_post_klaarzetten( $item, $pad, $oud ) {

	$id = isset( $item['id'] ) ? preg_replace( '/[^0-9A-Za-z_]/', '', (string) $item['id'] ) : '';
	if ( '' === $id ) {
		return null;
	}

	$permalink = isset( $item['permalink'] ) ? esc_url_raw( $item['permalink'] ) : '';
	if ( ! preg_match( '#^https://(www\.)?instagram\.com/#', $permalink ) ) {
		$permalink = '';
	}

	// Vierkante versies van Behold. Bij een video of carrousel is dat de
	// omslag of de eerste foto. Zonder sizes: de originele afbeelding.
	$bronnen = array();
	if ( isset( $item['sizes']['small']['mediaUrl'] ) ) {
		$bronnen['klein'] = $item['sizes']['small']['mediaUrl'];
	}
	if ( isset( $item['sizes']['medium']['mediaUrl'] ) ) {
		$bronnen['middel'] = $item['sizes']['medium']['mediaUrl'];
	}
	if ( empty( $bronnen ) ) {
		$los = '';
		if ( isset( $item['thumbnailUrl'] ) ) {
			$los = $item['thumbnailUrl'];
		} elseif ( isset( $item['mediaUrl'] ) && ( ! isset( $item['mediaType'] ) || 'VIDEO' !== $item['mediaType'] ) ) {
			$los = $item['mediaUrl'];
		}
		if ( $los ) {
			$bronnen['middel'] = $los;
		}
	}
	if ( empty( $bronnen ) ) {
		return null;
	}

	$fotos = array();
	foreach ( $bronnen as $maat => $bron ) {

		// Al eerder opgehaald en nog aanwezig: niet opnieuw downloaden.
		if ( isset( $oud[ $id ]['fotos'][ $maat ] ) ) {
			$vorige = $oud[ $id ]['fotos'][ $maat ];
			if ( file_exists( $pad . '/' . basename( $vorige['bestand'] ) ) ) {
				$fotos[ $maat ] = $vorige;
				continue;
			}
		}

		$foto = mm_instagram_foto_downloaden( $bron, $pad, $id . '-' . $maat );
		if ( $foto ) {
			$fotos[ $maat ] = $foto;
		}
	}

	if ( empty( $fotos ) ) {
		return null;
	}

	$tekst = '';
	if ( isset( $item['prunedCaption'] ) ) {
		$tekst = (string) $item['prunedCaption'];
	} elseif ( isset( $item['caption'] ) ) {
		$tekst = (string) $item['caption'];
	}

	return array(
		'id'        => $id,
		'permalink' => $permalink,
		'type'      => isset( $item['mediaType'] ) ? sanitize_key( $item['mediaType'] ) : 'image',
		'datum'     => isset( $item['timestamp'] ) ? (int) strtotime( $item['timestamp'] ) : 0,
		'alt'       => mm_instagram_alt( $tekst ),
		'fotos'     => $fotos,
	);
}

/**
 * Downloadt een foto naar de map, alleen van de hosts van Behold en
 * Instagram en alleen als het echt een afbeelding is.
 *
 * @return array|null array( bestand, breedte, hoogte )
 */
function mm_instagram_foto_downloaden( $bron, $pad, $naam ) {

	$host = strtolower( (string) wp_parse_url( $bron, PHP_URL_HOST ) );
	$mag  = 'https' === wp_parse_url( $bron, PHP_URL_SCHEME )
		&& ( 'hop.behold.pictures' === $host
			|| (bool) preg_match( '/(^|\.)(behold\.pictures|cdninstagram\.com|fbcdn\.net)$/', $host ) );
	if ( ! $mag ) {
		return null;
	}

	$tijdelijk = download_url( $bron, 20 );
	if ( is_wp_error( $tijdelijk ) ) {
		return null;
	}

	$info  = @getimagesize( $tijdelijk ); // phpcs:ignore
	$types = array(
		IMAGETYPE_JPEG => 'jpg',
		IMAGETYPE_PNG  => 'png',
		IMAGETYPE_WEBP => 'webp',
	);
	if ( ! $info || ! isset( $types[ $info[2] ] ) ) {
		@unlink( $tijdelijk ); // phpcs:ignore
		return null;
	}

	$bestand = $naam . '.' . $types[ $info[2] ];
	if ( ! @rename( $tijdelijk, $pad . '/' . $bestand ) ) { // phpcs:ignore
		if ( ! @copy( $tijdelijk, $pad . '/' . $bestand ) ) { // phpcs:ignore
			@unlink( $tijdelijk ); // phpcs:ignore
			return null;
		}
		@unlink( $tijdelijk ); // phpcs:ignore
	}
	@chmod( $pad . '/' . $bestand, 0644 ); // phpcs:ignore

	return array(
		'bestand' => $bestand,
		'breedte' => (int) $info[0],
		'hoogte'  => (int) $info[1],
	);
}

/**
 * Alt-tekst uit het bijschrift: de eerste regel met tekst, hooguit zo'n
 * 120 tekens.
 */
function mm_instagram_alt( $tekst ) {
	$tekst = wp_strip_all_tags( $tekst );
	$regel = '';
	foreach ( preg_split( '/\R/u', $tekst ) as $kandidaat ) {
		$kandidaat = trim( preg_replace( '/\s+/u', ' ', $kandidaat ) );
		if ( '' !== $kandidaat ) {
			$regel = $kandidaat;
			break;
		}
	}
	if ( '' === $regel ) {
		return 'Instagram-bericht';
	}
	$lengte = function_exists( 'mb_strlen' ) ? mb_strlen( $regel ) : strlen( $regel );
	if ( $lengte > 120 ) {
		$kort  = function_exists( 'mb_substr' ) ? mb_substr( $regel, 0, 120 ) : substr( $regel, 0, 120 );
		$spatie = strrpos( $kort, ' ' );
		$regel = rtrim( false !== $spatie ? substr( $kort, 0, $spatie ) : $kort, " ,.;:" ) . '…';
	}
	return $regel;
}

/**
 * Telt een aanvraag bij Behold, per maand. Houdt de laatste 6 maanden.
 */
function mm_instagram_tel_aanvraag() {
	$status = mm_instagram_status();
	$maand  = wp_date( 'Y-m' );
	$status['teller'][ $maand ] = ( isset( $status['teller'][ $maand ] ) ? (int) $status['teller'][ $maand ] : 0 ) + 1;
	krsort( $status['teller'] );
	$status['teller'] = array_slice( $status['teller'], 0, 6, true );
	update_option( MM_INSTAGRAM_STATUS, array_merge( mm_instagram_status(), array( 'teller' => $status['teller'] ) ), false );
}


/* -------------------------------------------------------------------------
 * Shortcode [mm_instagram]
 * ---------------------------------------------------------------------- */

add_shortcode( 'mm_instagram', 'mm_instagram_shortcode' );

/**
 * Breekpunten van Elementor (tablet en mobiel), met de standaard als
 * Elementor er niet is.
 */
function mm_instagram_breekpunten() {
	$tablet = 1024;
	$mobiel = 767;
	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->breakpoints ) ) {
		try {
			$punten = \Elementor\Plugin::$instance->breakpoints->get_breakpoints();
			if ( isset( $punten['tablet'] ) ) {
				$tablet = (int) $punten['tablet']->get_value();
			}
			if ( isset( $punten['mobile'] ) ) {
				$mobiel = (int) $punten['mobile']->get_value();
			}
		} catch ( \Throwable $e ) {
			// Standaard houden.
		}
	}
	return array( $tablet, $mobiel );
}

function mm_instagram_shortcode( $atts ) {

	$atts = shortcode_atts(
		array(
			'aantal'          => 0,
			'kolommen'        => 3,
			'kolommen_tablet' => '',
			'kolommen_mobiel' => '',
			'ruimte'          => 8,
			'afronding'       => 0,
			'verhouding'      => 'auto',
		),
		$atts,
		'mm_instagram'
	);

	// LiteSpeed: deze pagina toont de feed, zodat een nieuwe feed alleen
	// deze pagina's purget. Ook met een lege feed, anders blijft een pagina
	// leeg in de cache staan als de eerste posts binnenkomen.
	do_action( 'litespeed_tag_add', 'mm_instagram' );

	$feed  = mm_instagram_feed();
	$posts = $feed['posts'];
	if ( empty( $posts ) ) {
		return current_user_can( 'manage_options' ) ? '<!-- mm_instagram: nog geen posts opgehaald -->' : '';
	}

	$aantal = (int) $atts['aantal'];
	if ( $aantal > 0 ) {
		$posts = array_slice( $posts, 0, $aantal );
	}

	$kol   = max( 1, min( 12, (int) $atts['kolommen'] ) );
	$kol_t = '' === $atts['kolommen_tablet'] ? $kol : max( 1, min( 12, (int) $atts['kolommen_tablet'] ) );
	$kol_m = '' === $atts['kolommen_mobiel'] ? $kol_t : max( 1, min( 12, (int) $atts['kolommen_mobiel'] ) );

	list( $tablet, $mobiel ) = mm_instagram_breekpunten();
	$map  = mm_instagram_map();
	$html = '';

	static $css_gedaan = false;
	if ( ! $css_gedaan ) {
		$css_gedaan = true;
		$html      .= '<style id="mm-instagram-css">'
			. '.mm-instagram{display:grid;grid-template-columns:repeat(var(--mm-ig-kol),minmax(0,1fr));gap:var(--mm-ig-ruimte);margin:0;padding:0;list-style:none}'
			. '.mm-instagram li{margin:0;padding:0}'
			. '.mm-instagram a{display:block;aspect-ratio:var(--mm-ig-verhouding,1/1);overflow:hidden;border-radius:var(--mm-ig-afronding)}'
			. '.mm-instagram img{display:block;width:100%;height:100%;object-fit:cover;transition:opacity .2s}'
			. '.mm-instagram a:hover img{opacity:.85}'
			. '@media (max-width:' . (int) $tablet . 'px){.mm-instagram{grid-template-columns:repeat(var(--mm-ig-kol-t),minmax(0,1fr))}}'
			. '@media (max-width:' . (int) $mobiel . 'px){.mm-instagram{grid-template-columns:repeat(var(--mm-ig-kol-m),minmax(0,1fr))}}'
			. '</style>';
	}

	// Verhouding van de tegels: "auto" volgt de eerste foto (Instagram en
	// Behold leveren tegenwoordig vaak 4:5), anders bijvoorbeeld "1/1".
	$verhouding = '1 / 1';
	if ( preg_match( '#^\s*(\d{1,4})\s*[/:]\s*(\d{1,4})\s*$#', (string) $atts['verhouding'], $deel ) && (int) $deel[1] > 0 && (int) $deel[2] > 0 ) {
		$verhouding = (int) $deel[1] . ' / ' . (int) $deel[2];
	} else {
		$eerste = reset( $posts );
		$foto   = isset( $eerste['fotos']['middel'] ) ? $eerste['fotos']['middel'] : $eerste['fotos']['klein'];
		if ( ! empty( $foto['breedte'] ) && ! empty( $foto['hoogte'] ) ) {
			$verhouding = (int) $foto['breedte'] . ' / ' . (int) $foto['hoogte'];
		}
	}

	$stijl = sprintf(
		'--mm-ig-kol:%d;--mm-ig-kol-t:%d;--mm-ig-kol-m:%d;--mm-ig-ruimte:%dpx;--mm-ig-afronding:%dpx;--mm-ig-verhouding:%s',
		$kol,
		$kol_t,
		$kol_m,
		max( 0, (int) $atts['ruimte'] ),
		max( 0, (int) $atts['afronding'] ),
		$verhouding
	);

	$sizes = sprintf(
		'(max-width: %1$dpx) %2$dvw, (max-width: %3$dpx) %4$dvw, 400px',
		$mobiel,
		(int) ceil( 100 / $kol_m ),
		$tablet,
		(int) ceil( 100 / $kol_t )
	);

	$html .= '<ul class="mm-instagram" style="' . esc_attr( $stijl ) . '">';

	foreach ( $posts as $post ) {

		$fotos = $post['fotos'];
		$basis = isset( $fotos['middel'] ) ? $fotos['middel'] : $fotos['klein'];
		$src   = $map['url'] . '/' . rawurlencode( $basis['bestand'] );

		$srcset = array();
		foreach ( array( 'klein', 'middel' ) as $maat ) {
			if ( isset( $fotos[ $maat ] ) ) {
				$srcset[] = esc_url( $map['url'] . '/' . rawurlencode( $fotos[ $maat ]['bestand'] ) ) . ' ' . (int) $fotos[ $maat ]['breedte'] . 'w';
			}
		}

		$img = sprintf(
			'<img src="%1$s"%2$s width="%3$d" height="%4$d" alt="%5$s" loading="lazy" decoding="async">',
			esc_url( $src ),
			count( $srcset ) > 1 ? ' srcset="' . esc_attr( implode( ', ', $srcset ) ) . '" sizes="' . esc_attr( $sizes ) . '"' : '',
			(int) $basis['breedte'],
			(int) $basis['hoogte'],
			esc_attr( $post['alt'] )
		);

		$html .= '<li class="mm-instagram__post mm-instagram__post--' . esc_attr( $post['type'] ) . '">';
		if ( $post['permalink'] ) {
			$html .= '<a href="' . esc_url( $post['permalink'] ) . '" target="_blank" rel="noopener">' . $img . '</a>';
		} else {
			$html .= $img;
		}
		$html .= '</li>';
	}

	$html .= '</ul>';

	return $html;
}


/* -------------------------------------------------------------------------
 * Beheer: Instellingen > Instagram-feed
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'mm_instagram_menu' );

function mm_instagram_menu() {
	add_options_page( 'Instagram-feed', 'Instagram-feed', 'manage_options', 'mm-instagram', 'mm_instagram_scherm' );
}

add_action( 'admin_post_mm_instagram_opslaan', 'mm_instagram_opslaan' );
add_action( 'admin_post_mm_instagram_nu', 'mm_instagram_nu' );

function mm_instagram_terug( $melding ) {
	wp_safe_redirect( add_query_arg( 'mm_ig', rawurlencode( $melding ), admin_url( 'options-general.php?page=mm-instagram' ) ) );
	exit;
}

function mm_instagram_opslaan() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Geen toegang.' );
	}
	check_admin_referer( 'mm_instagram_opslaan' );

	$url    = isset( $_POST['mm_ig_url'] ) ? trim( esc_url_raw( wp_unslash( $_POST['mm_ig_url'] ) ) ) : '';
	$aantal = isset( $_POST['mm_ig_aantal'] ) ? max( 1, min( MM_INSTAGRAM_MAX, (int) $_POST['mm_ig_aantal'] ) ) : 6;

	if ( '' !== $url && ! mm_instagram_url_geldig( $url ) ) {
		mm_instagram_terug( 'fout:Dat is geen JSON-feed van Behold. Die begint met https://feeds.behold.so/' );
	}

	$oud = mm_instagram_instellingen();
	update_option( MM_INSTAGRAM_INSTELLINGEN, array( 'url' => $url, 'aantal' => $aantal ), true ); // init leest hem elke aanvraag
	mm_instagram_inplannen();

	if ( '' === $url ) {
		mm_instagram_terug( 'ok:Opgeslagen. Zonder feed-URL wordt er niets opgehaald.' );
	}

	// Alleen meteen ophalen als er iets veranderd is of er nog niets staat.
	if ( $url !== $oud['url'] || $aantal !== $oud['aantal'] || empty( mm_instagram_feed()['posts'] ) ) {
		$uitkomst = mm_instagram_ophalen();
		mm_instagram_terug( is_wp_error( $uitkomst ) ? 'fout:Opgeslagen, maar ophalen lukte niet: ' . $uitkomst->get_error_message() : 'ok:Opgeslagen en opgehaald.' );
	}

	mm_instagram_terug( 'ok:Opgeslagen.' );
}

function mm_instagram_nu() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Geen toegang.' );
	}
	check_admin_referer( 'mm_instagram_nu' );
	$uitkomst = mm_instagram_ophalen();
	mm_instagram_terug( is_wp_error( $uitkomst ) ? 'fout:' . $uitkomst->get_error_message() : 'ok:Opgehaald.' );
}

function mm_instagram_datum( $timestamp ) {
	return $timestamp ? wp_date( 'j F Y, H:i', $timestamp ) : 'nog nooit';
}

/**
 * Korte regel voor de moduletabel van de toolkit.
 */
function mm_instagram_status_regel() {
	$instellingen = mm_instagram_instellingen();
	if ( '' === $instellingen['url'] ) {
		return 'Nog geen feed-URL ingevuld';
	}
	$feed   = mm_instagram_feed();
	$status = mm_instagram_status();
	if ( $status['fout'] ) {
		return '<span style="color:#b32d2e;">Laatste poging mislukt: ' . esc_html( $status['fout'] ) . '</span>';
	}
	return esc_html( count( $feed['posts'] ) . ' posts, opgehaald ' . mm_instagram_datum( $feed['opgehaald'] ) );
}

function mm_instagram_scherm() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$instellingen = mm_instagram_instellingen();
	$feed         = mm_instagram_feed();
	$status       = mm_instagram_status();
	$map          = mm_instagram_map();

	echo '<div class="wrap"><h1>Instagram-feed</h1>';

	if ( ! empty( $_GET['mm_ig'] ) ) {
		$melding = sanitize_text_field( wp_unslash( $_GET['mm_ig'] ) );
		$fout    = 0 === strpos( $melding, 'fout:' );
		$tekst   = preg_replace( '/^(ok|fout):/', '', $melding );
		echo '<div class="notice notice-' . ( $fout ? 'error' : 'success' ) . ' is-dismissible"><p>' . esc_html( $tekst ) . '</p></div>';
	}

	echo '<p style="max-width:760px;">Plak hier de JSON-feed-URL uit Behold (Feeds, de feed openen, JSON). De site haalt de feed twee keer per dag op en zet de foto\'s op de eigen server. Bezoekers laden niets van Behold of Instagram.</p>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="mm_instagram_opslaan">';
	wp_nonce_field( 'mm_instagram_opslaan' );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="mm_ig_url">Feed-URL</label></th><td><input type="url" class="regular-text" id="mm_ig_url" name="mm_ig_url" value="' . esc_attr( $instellingen['url'] ) . '" placeholder="https://feeds.behold.so/..."></td></tr>';
	echo '<tr><th scope="row"><label for="mm_ig_aantal">Aantal posts</label></th><td><input type="number" min="1" max="' . (int) MM_INSTAGRAM_MAX . '" id="mm_ig_aantal" name="mm_ig_aantal" value="' . (int) $instellingen['aantal'] . '" class="small-text"> <span class="description">Op het gratis plan van Behold zitten er hooguit 6 in de feed.</span></td></tr>';
	echo '</tbody></table>';
	submit_button( 'Opslaan' );
	echo '</form>';

	echo '<h2>Status</h2>';
	echo '<table class="widefat striped" style="max-width:760px;"><tbody>';
	echo '<tr><td style="width:240px;">Opgehaald</td><td>' . esc_html( mm_instagram_datum( $feed['opgehaald'] ) ) . ', ' . count( $feed['posts'] ) . ' posts</td></tr>';
	echo '<tr><td>Laatste poging</td><td>' . esc_html( mm_instagram_datum( $status['poging'] ) ) . ( $status['fout'] ? '<br><span style="color:#b32d2e;">' . esc_html( $status['fout'] ) . '</span>' : '' ) . '</td></tr>';
	$volgende = wp_next_scheduled( 'mm_instagram_ophalen' );
	echo '<tr><td>Volgende keer</td><td>' . esc_html( $volgende ? mm_instagram_datum( $volgende ) : 'niet ingepland' ) . '</td></tr>';
	$teller = array();
	foreach ( $status['teller'] as $maand => $aantal ) {
		$teller[] = $maand . ': ' . (int) $aantal;
	}
	echo '<tr><td>Aanvragen bij Behold</td><td>' . esc_html( $teller ? implode( ', ', $teller ) : 'nog geen' ) . '<br><span class="description">Elke aanvraag telt bij Behold als één view. Het gratis plan heeft er 1.200 per maand.</span></td></tr>';
	echo '</tbody></table>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:12px;">';
	echo '<input type="hidden" name="action" value="mm_instagram_nu">';
	wp_nonce_field( 'mm_instagram_nu' );
	submit_button( 'Nu ophalen', 'secondary', 'submit', false, '' === $instellingen['url'] ? array( 'disabled' => 'disabled' ) : null );
	echo '</form>';

	if ( $feed['posts'] ) {
		echo '<h2>Voorbeeld</h2><div style="display:flex;gap:8px;flex-wrap:wrap;max-width:760px;">';
		foreach ( $feed['posts'] as $post ) {
			$foto = isset( $post['fotos']['klein'] ) ? $post['fotos']['klein'] : $post['fotos']['middel'];
			echo '<img src="' . esc_url( $map['url'] . '/' . rawurlencode( $foto['bestand'] ) ) . '" alt="' . esc_attr( $post['alt'] ) . '" title="' . esc_attr( $post['alt'] ) . '" style="width:110px;height:110px;object-fit:cover;">';
		}
		echo '</div>';
	}

	echo '<h2>Gebruik</h2>';
	echo '<p>Zet een Shortcode-widget van Elementor waar de feed moet komen, bijvoorbeeld in de footer:</p>';
	echo '<p><code>[mm_instagram]</code></p>';
	echo '<p>Opties: <code>aantal</code> (standaard alles), <code>kolommen</code> (3), <code>kolommen_tablet</code>, <code>kolommen_mobiel</code> (standaard gelijk aan de maat erboven), <code>ruimte</code> (8, in px), <code>afronding</code> (0, in px) en <code>verhouding</code> (auto: volgt de foto\'s, of bijvoorbeeld 1/1 voor vierkant). Bijvoorbeeld:</p>';
	echo '<p><code>[mm_instagram aantal="6" kolommen="3" kolommen_mobiel="3" ruimte="6"]</code></p>';

	echo '</div>';
}
