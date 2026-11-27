<?php
/**
 * Mediamora Toolkit, module: Lettertype voorladen
 * Wordt alleen geladen als de module aanstaat.
 *
 * Elementor zet Google Fonts lokaal neer (optie elementor_local_google_fonts)
 * met font-display: swap. Op mobiel komt het tekstlettertype daardoor pas na
 * de eerste weergave binnen: de alinea's worden opnieuw opgemaakt en de
 * pagina verspringt (CLS). Deze module zet één preload in de head voor het
 * woff2-bestand van het tekstlettertype uit de kit, zodat het er al is als de
 * tekst voor het eerst wordt getekend.
 *
 * Welk bestand: de typografie "text" uit de actieve kit (zonder lettertype
 * "secondary"), met het gewicht en de stijl die daar staan. Daarvan het
 * @font-face-blok uit uploads/elementor/google-fonts/css/<familie>.css met de
 * latin-subset (unicode-range begint met U+0000-00FF). Op de stijl letten: in
 * die CSS staan de cursieve blokken vóór de rechte, en bij een variabel
 * lettertype hebben alle gewichten van een stijl hetzelfde bestand.
 *
 * Bewust maar één bestand: meer preloads concurreren met de CSS en met de
 * preload van de hero, en maken de pagina juist trager. Met het vinkje op het
 * tabblad Snelheid komt het kopletterype ("primary") erbij, als dat een ander
 * bestand is.
 *
 * Doet niets zonder lokale Google Fonts, bij een systeem- of eigen lettertype,
 * of als het bestand niet op schijf staat.
 *
 * Staat standaard uit: de preload deelt de bandbreedte met de CSS en maakt de
 * eerste weergave iets trager (op geertjedokter.nl +0,24 s FCP). Alleen
 * aanzetten als mobiele PageSpeed een layout shift door een webfont laat zien.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optie met het vinkje "ook het kopletterype voorladen", 1 of 0.
 */
const MM_FONT_PRELOAD_KOP_OPTIE = 'mm_font_preload_kop';

/**
 * Transient met de gevonden bestanden. De sleutel waarmee ze berekend zijn
 * staat erin, zie mm_font_preload_bestanden().
 */
const MM_FONT_PRELOAD_TRANSIENT = 'mm_font_preload';

/**
 * Ook het kopletterype voorladen? Standaard niet.
 */
function mm_font_preload_kop_aan() {
	return (bool) get_option( MM_FONT_PRELOAD_KOP_OPTIE, 0 );
}

/**
 * Alleen op de voorkant: niet in wp-admin, niet bij REST, AJAX, feeds en niet
 * in de editor of het voorbeeld van Elementor.
 */
function mm_font_preload_nodig() {

	if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_feed() ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Alleen gelezen om te bepalen wat er getoond wordt, er wordt niets opgeslagen.
	if ( isset( $_GET['elementor-preview'] ) ) {
		return false;
	}

	if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) ) {
		return false;
	}

	$elementor = \Elementor\Plugin::$instance;
	if ( isset( $elementor->preview ) && method_exists( $elementor->preview, 'is_preview_mode' ) && $elementor->preview->is_preview_mode() ) {
		return false;
	}
	if ( isset( $elementor->editor ) && method_exists( $elementor->editor, 'is_edit_mode' ) && $elementor->editor->is_edit_mode() ) {
		return false;
	}

	return true;
}

/**
 * De typografie uit de actieve kit met dit _id, of null. Alleen als er een
 * lettertype in staat.
 *
 * @param array  $instellingen _elementor_page_settings van de kit.
 * @param string $id           Bijvoorbeeld text, secondary of primary.
 * @return array|null Array met familie, gewicht en stijl.
 */
function mm_font_preload_kit_typografie( $instellingen, $id ) {

	if ( empty( $instellingen['system_typography'] ) || ! is_array( $instellingen['system_typography'] ) ) {
		return null;
	}

	foreach ( $instellingen['system_typography'] as $regel ) {
		if ( ! is_array( $regel ) || ! isset( $regel['_id'] ) || $id !== $regel['_id'] ) {
			continue;
		}
		if ( empty( $regel['typography_font_family'] ) || ! is_string( $regel['typography_font_family'] ) ) {
			return null;
		}
		return array(
			'familie' => $regel['typography_font_family'],
			'gewicht' => mm_font_preload_gewicht( isset( $regel['typography_font_weight'] ) ? $regel['typography_font_weight'] : '' ),
			'stijl'   => ( isset( $regel['typography_font_style'] ) && in_array( $regel['typography_font_style'], array( 'italic', 'oblique' ), true ) ) ? 'italic' : 'normal',
		);
	}

	return null;
}

/**
 * Gewicht uit de kit als getal. Leeg of normal is 400, bold is 700.
 *
 * @param mixed $gewicht
 * @return int
 */
function mm_font_preload_gewicht( $gewicht ) {
	$gewicht = strtolower( trim( (string) $gewicht ) );
	if ( 'bold' === $gewicht ) {
		return 700;
	}
	if ( ctype_digit( $gewicht ) && (int) $gewicht >= 1 && (int) $gewicht <= 1000 ) {
		return (int) $gewicht;
	}
	return 400;
}

/**
 * Zoekt in de CSS van Elementor het woff2-bestand van de latin-subset met
 * deze stijl en dit gewicht. Bij een variabel lettertype kan font-weight een
 * bereik zijn, zoals 100 900.
 *
 * @param string $css     Inhoud van google-fonts/css/<familie>.css.
 * @param int    $gewicht
 * @param string $stijl   normal of italic.
 * @return string De url uit src, of een lege string.
 */
function mm_font_preload_zoek_url( $css, $gewicht, $stijl ) {

	if ( ! preg_match_all( '#@font-face\s*\{([^}]*)\}#i', (string) $css, $blokken ) ) {
		return '';
	}

	foreach ( $blokken[1] as $blok ) {

		if ( ! preg_match( '#unicode-range\s*:\s*U\+0000-00FF\b#i', $blok ) ) {
			continue;
		}

		$blok_stijl = preg_match( '#font-style\s*:\s*([a-z]+)#i', $blok, $s ) ? strtolower( $s[1] ) : 'normal';
		if ( 'oblique' === $blok_stijl ) {
			$blok_stijl = 'italic';
		}
		if ( $blok_stijl !== $stijl ) {
			continue;
		}

		if ( ! preg_match( '#font-weight\s*:\s*(\d+)(?:\s+(\d+))?#i', $blok, $w ) ) {
			continue;
		}
		$van = (int) $w[1];
		$tot = isset( $w[2] ) && '' !== $w[2] ? (int) $w[2] : $van;
		if ( $gewicht < $van || $gewicht > $tot ) {
			continue;
		}

		if ( preg_match( '#url\(\s*([\'"]?)([^\'")\s]+\.woff2)\1\s*\)#i', $blok, $u ) ) {
			return $u[2];
		}
	}

	return '';
}

/**
 * Pad op schijf bij een url in de uploadmap, of een lege string. Alleen
 * bestanden onder uploads/elementor/google-fonts/fonts/. Het schema telt niet
 * mee, want de CSS kan http hebben terwijl de site op https draait.
 *
 * @param string $url
 * @param array  $uploads Uitkomst van wp_upload_dir().
 * @return string
 */
function mm_font_preload_pad( $url, $uploads ) {

	$zonder_schema = function ( $adres ) {
		return preg_replace( '#^https?:#i', '', (string) $adres );
	};

	$basis = trailingslashit( $zonder_schema( $uploads['baseurl'] ) ) . 'elementor/google-fonts/fonts/';
	$adres = $zonder_schema( strtok( $url, '?#' ) );

	if ( 0 !== strpos( $adres, $basis ) ) {
		return '';
	}

	$bestand = substr( $adres, strlen( $basis ) );
	if ( '' === $bestand || false !== strpos( $bestand, '/' ) || false !== strpos( $bestand, '..' ) ) {
		return '';
	}

	return trailingslashit( $uploads['basedir'] ) . 'elementor/google-fonts/fonts/' . $bestand;
}

/**
 * Het css-bestand van Elementor bij deze familie, of een lege string.
 * Bestandsnaam: de familienaam in kleine letters zonder spaties, zoals
 * montserrat.css of kleeone.css.
 *
 * @param string $familie
 * @param array  $uploads Uitkomst van wp_upload_dir().
 * @return string
 */
function mm_font_preload_css_pad( $familie, $uploads ) {
	$naam = strtolower( str_replace( ' ', '', $familie ) );
	if ( '' === $naam || ! preg_match( '#^[a-z0-9_-]+$#', $naam ) ) {
		return '';
	}
	return trailingslashit( $uploads['basedir'] ) . 'elementor/google-fonts/css/' . $naam . '.css';
}

/**
 * Is dit een Google Font volgens Elementor? Een systeem- of eigen lettertype
 * staat niet lokaal in google-fonts.
 *
 * @param string $familie
 * @return bool
 */
function mm_font_preload_google_font( $familie ) {
	if ( ! class_exists( '\Elementor\Fonts' ) || ! method_exists( '\Elementor\Fonts', 'get_font_type' ) ) {
		return false;
	}
	return 'googlefonts' === \Elementor\Fonts::get_font_type( $familie );
}

/**
 * De lettertypen uit de kit die in aanmerking komen: text (anders secondary),
 * en met het vinkje ook primary. Alleen lokale Google Fonts.
 *
 * @return array[] Per typografie: familie, gewicht, stijl en css (pad).
 */
function mm_font_preload_kandidaten( $kit_id, $uploads ) {

	if ( '1' !== (string) get_option( 'elementor_local_google_fonts', '0' ) || '0' === (string) get_option( 'elementor_google_font', '1' ) ) {
		return array();
	}

	$instellingen = get_post_meta( $kit_id, '_elementor_page_settings', true );
	if ( ! is_array( $instellingen ) ) {
		return array();
	}

	$tekst = mm_font_preload_kit_typografie( $instellingen, 'text' );
	if ( ! $tekst ) {
		$tekst = mm_font_preload_kit_typografie( $instellingen, 'secondary' );
	}

	$lijst = array( $tekst );
	if ( mm_font_preload_kop_aan() ) {
		$lijst[] = mm_font_preload_kit_typografie( $instellingen, 'primary' );
	}

	$kandidaten = array();
	foreach ( array_filter( $lijst ) as $typografie ) {
		if ( ! mm_font_preload_google_font( $typografie['familie'] ) ) {
			continue;
		}
		$css = mm_font_preload_css_pad( $typografie['familie'], $uploads );
		if ( '' !== $css && is_readable( $css ) ) {
			$typografie['css'] = $css;
			$kandidaten[]      = $typografie;
		}
	}

	return $kandidaten;
}

/**
 * De url's om voor te laden, hooguit twee en zonder dubbele. Gecachet in een
 * transient; de sleutel bevat de kit, het moment waarop die is opgeslagen, het
 * vinkje en de filemtime van de css-bestanden. Genereert Elementor de fonts
 * opnieuw (met een andere hash in de bestandsnaam), dan verandert het
 * css-bestand en wordt er opnieuw gezocht.
 *
 * @return string[]
 */
function mm_font_preload_bestanden() {

	$kit_id = (int) get_option( 'elementor_active_kit', 0 );
	if ( ! $kit_id ) {
		return array();
	}

	$uploads = wp_upload_dir( null, false );
	if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
		return array();
	}

	// De css-paden hangen alleen van de kit af. Voor de sleutel de filemtime
	// van beide mogelijke bestanden, zodat de cache zonder te parsen te
	// controleren is.
	$instellingen = get_post_meta( $kit_id, '_elementor_page_settings', true );
	$mtimes       = array();
	foreach ( array( 'text', 'secondary', 'primary' ) as $id ) {
		$typografie = is_array( $instellingen ) ? mm_font_preload_kit_typografie( $instellingen, $id ) : null;
		if ( $typografie ) {
			$css      = mm_font_preload_css_pad( $typografie['familie'], $uploads );
			$mtimes[] = '' !== $css && file_exists( $css ) ? (int) filemtime( $css ) : 0;
		}
	}

	$sleutel = md5(
		implode(
			'|',
			array(
				$kit_id,
				(string) get_post_field( 'post_modified_gmt', $kit_id ),
				mm_font_preload_kop_aan() ? 1 : 0,
				(string) get_option( 'elementor_local_google_fonts', '0' ),
				implode( ',', $mtimes ),
			)
		)
	);

	$cache = get_transient( MM_FONT_PRELOAD_TRANSIENT );
	if ( is_array( $cache ) && isset( $cache['sleutel'], $cache['urls'] ) && $sleutel === $cache['sleutel'] && is_array( $cache['urls'] ) ) {
		return $cache['urls'];
	}

	$urls = array();
	foreach ( mm_font_preload_kandidaten( $kit_id, $uploads ) as $typografie ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Lokaal CSS-bestand van Elementor in uploads, geen URL.
		$url = mm_font_preload_zoek_url( (string) file_get_contents( $typografie['css'] ), $typografie['gewicht'], $typografie['stijl'] );
		if ( '' !== $url && ! in_array( $url, $urls, true ) ) {
			$urls[] = $url;
		}
	}

	set_transient( MM_FONT_PRELOAD_TRANSIENT, array( 'sleutel' => $sleutel, 'urls' => $urls ), DAY_IN_SECONDS );

	return $urls;
}

/**
 * Zet de preload in de head, zo vroeg mogelijk. Een bestand dat niet (meer)
 * op schijf staat wordt overgeslagen, zodat er nooit een preload naar een 404
 * gaat. LiteSpeed laat een link-preload ongemoeid, ook met Guest Mode.
 */
add_action( 'wp_head', 'mm_font_preload_head', 1 );

function mm_font_preload_head() {

	if ( ! mm_font_preload_nodig() ) {
		return;
	}

	$uploads = wp_upload_dir( null, false );

	foreach ( mm_font_preload_bestanden() as $url ) {
		$pad = mm_font_preload_pad( $url, $uploads );
		if ( '' === $pad || ! is_file( $pad ) ) {
			continue;
		}
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( set_url_scheme( $url ) )
		);
	}
}

/**
 * Kit opgeslagen: de cache weg. De sleutel bevat ook het moment van opslaan,
 * maar zo blijft er geen oude waarde hangen.
 */
add_action( 'save_post', 'mm_font_preload_kit_opgeslagen' );

function mm_font_preload_kit_opgeslagen( $post_id ) {
	if ( (int) $post_id === (int) get_option( 'elementor_active_kit', 0 ) ) {
		delete_transient( MM_FONT_PRELOAD_TRANSIENT );
	}
}

add_action( 'update_option_elementor_active_kit', 'mm_font_preload_cache_weg' );
add_action( 'update_option_elementor_local_google_fonts', 'mm_font_preload_cache_weg' );
add_action( 'update_option_' . MM_FONT_PRELOAD_KOP_OPTIE, 'mm_font_preload_cache_weg' );

function mm_font_preload_cache_weg() {
	delete_transient( MM_FONT_PRELOAD_TRANSIENT );
}

/**
 * Regel voor de statuskolom: welk bestand er wordt voorgeladen, of waarom
 * niet.
 */
function mm_font_preload_status_regel() {

	if ( '1' !== (string) get_option( 'elementor_local_google_fonts', '0' ) ) {
		return 'Doet niets: Google Fonts staan niet lokaal in Elementor';
	}

	$uploads = wp_upload_dir( null, false );
	$namen   = array();
	foreach ( mm_font_preload_bestanden() as $url ) {
		$pad = mm_font_preload_pad( $url, $uploads );
		if ( '' !== $pad && is_file( $pad ) ) {
			$namen[] = basename( $pad );
		}
	}

	if ( ! $namen ) {
		return 'Geen bestand gevonden: geen lokaal Google Font in de kit';
	}

	return 'Laadt voor: ' . esc_html( implode( ', ', $namen ) );
}
