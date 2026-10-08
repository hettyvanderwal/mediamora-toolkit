<?php
/**
 * Mediamora Toolkit, module: Afbeeldingen, ruimte en voorrang
 * Vervangt de testcode in functions.php (mm_ig_elementor_img_attrs, intens-gezond.nl).
 * Wordt alleen geladen als de module aanstaat.
 *
 * Twee onderdelen, allebei op de <img> van de afbeeldingswidget van Elementor:
 *
 * 1. Afmetingen. Met een eigen uitsnede (image_size custom, bijvoorbeeld
 *    1000x1333) zet Elementor het bestand in uploads/elementor/thumbs en geeft
 *    de <img> geen width en height mee. Zolang EWWW Lazy Load alles lazyloadt
 *    valt dat niet op, want EWWW zet een tijdelijke PNG met de juiste maat
 *    neer. Maar de eerste afbeeldingen boven de vouw (EWWW-instelling
 *    ewww_image_optimizer_ll_abovethefold) krijgen die niet, en dan verspringt
 *    de pagina. De module leest de echte maat uit het bestand.
 *
 * 2. Voorrang. Afbeeldingswidgets in het eerste top-level element (container
 *    of sectie) van het hoofddocument krijgen loading="eager" en de class
 *    skip-lazy, zodat WordPress en EWWW ze niet lazyloaden. Dat is vaak de LCP.
 *
 * Bewust geen fetchpriority: Hero-preload geeft de achtergrond van de hero al
 * voorrang, en twee bronnen met high gaan met elkaar concurreren.
 *
 * Staat de testversie nog in functions.php (mm_ig_elementor_img_attrs), dan
 * doet deze module niets. Dat wordt pas in de filters gecontroleerd, want
 * functions.php laadt na de plugins. De status op het instellingenscherm
 * toont dan "de testcode in functions.php", zie thema_merkteken in
 * includes/modules.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Markering in _css_classes voor afbeeldingswidgets die voorrang krijgen.
 */
const MM_AFBEELDING_RUIMTE_KLASSE = 'mm-afbeelding-voorrang';

/**
 * Widgets waarvan de afbeelding voorrang kan krijgen. De uitgelichte
 * afbeelding van Elementor Pro is een afgeleide van de afbeeldingswidget en
 * staat vaak bovenaan een single-template.
 */
function mm_afbeelding_ruimte_widgets() {
	return array( 'image', 'theme-post-featured-image' );
}


/* -------------------------------------------------------------------------
 * Het hoofddocument
 *
 * Op een pagina of bericht is dat het Theme Builder-template van de locatie
 * single als dat er is, anders de pagina zelf. Op een archief (winkel,
 * productcategorie, blog, zoekresultaten) het template van de locatie
 * archive. Welk template, laten we Elementor Pro bepalen via zijn conditions
 * manager, net als Hero-preload: dat is precies het template dat Elementor
 * op deze pagina rendert.
 *
 * Header, footer, popups en loop-items hebben een eigen document-ID en
 * vallen er dus buiten. Op een pagina met een single-template valt ook de
 * eigen inhoud van de pagina (via de widget Berichtinhoud) erbuiten: die
 * staat niet bovenaan, het template wel.
 * ---------------------------------------------------------------------- */

/**
 * @return int ID van het hoofddocument, of 0 als dat er niet is.
 */
function mm_afbeelding_ruimte_hoofddocument() {

	static $id = null;
	if ( null !== $id ) {
		return $id;
	}

	// Pas bepalen als de query er is. Eerder (of in het beheer) niet onthouden.
	if ( is_admin() || ! did_action( 'wp' ) ) {
		return 0;
	}

	if ( is_singular() ) {
		$id = mm_afbeelding_ruimte_template( 'single' );
		if ( ! $id ) {
			$id = (int) get_queried_object_id();
		}
	} elseif ( is_archive() || is_home() || is_search() ) {
		$id = mm_afbeelding_ruimte_template( 'archive' );
	} else {
		$id = 0;
	}

	return $id;
}

/**
 * ID van het Theme Builder-template dat Elementor Pro voor deze locatie
 * rendert, of 0. Zelfde aanpak als mm_hero_preload_archieftemplate(), maar
 * een eigen kopie: Hero-preload kan uit staan.
 *
 * @param string $locatie 'single' of 'archive'.
 * @return int
 */
function mm_afbeelding_ruimte_template( $locatie ) {

	if ( ! class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
		return 0;
	}

	try {
		$module = \ElementorPro\Modules\ThemeBuilder\Module::instance();
		if ( ! $module || ! method_exists( $module, 'get_conditions_manager' ) ) {
			return 0;
		}

		$conditions = $module->get_conditions_manager();
		if ( ! $conditions || ! method_exists( $conditions, 'get_documents_for_location' ) ) {
			return 0;
		}

		// Gesorteerd op prioriteit; single en archive tonen er maar één.
		$documenten = $conditions->get_documents_for_location( $locatie );
		if ( empty( $documenten ) || ! is_array( $documenten ) ) {
			return 0;
		}

		$document = reset( $documenten );
		if ( is_object( $document ) && method_exists( $document, 'get_main_id' ) ) {
			return (int) $document->get_main_id();
		}

		return (int) key( $documenten );
	} catch ( \Throwable $e ) {
		return 0;
	}
}


/* -------------------------------------------------------------------------
 * Markeren
 *
 * Waarom zo: in het filter op de <img> (zie hieronder) is niet te zien in
 * welk document of welke sectie de widget staat. Dat filter krijgt alleen de
 * settings van de widget. Daarom markeren we vooraf, op
 * elementor/frontend/builder_content_data. Dat filter krijgt de
 * elementboom van één document met het document-ID erbij, dus daar is te
 * bepalen of het het hoofddocument is en wat het eerste top-level element is.
 *
 * De markering gaat in _css_classes. Dat is een geregistreerde control van
 * elke widget, dus hij komt gegarandeerd terug in de settings die het
 * <img>-filter krijgt (get_settings_for_display laat onbekende sleutels
 * weg). De class komt daardoor ook op de wrapper van de widget, wat geen
 * kwaad kan en bij het nakijken in de broncode laat zien welke widgets
 * voorrang kregen.
 *
 * Gebruikt de site Element Caching van Elementor, dan kan de oude HTML nog
 * uit die cache komen. Na het aanzetten dus de cache van Elementor legen
 * (Elementor > Gereedschap > Bestanden en data wissen), net als LiteSpeed.
 * ---------------------------------------------------------------------- */

function mm_afbeelding_ruimte_markeer( $data, $post_id = 0 ) {

	if ( function_exists( 'mm_ig_elementor_img_attrs' ) ) {
		return $data;
	}

	if ( ! is_array( $data ) || ! $data || ! $post_id ) {
		return $data;
	}

	$hoofd = mm_afbeelding_ruimte_hoofddocument();
	if ( ! $hoofd || (int) $post_id !== $hoofd ) {
		return $data;
	}

	// Het eerste top-level element. Op het hoogste niveau staan alleen
	// containers en secties; iets anders laten we liggen.
	foreach ( $data as $i => $element ) {
		if ( ! is_array( $element ) ) {
			continue;
		}
		if ( isset( $element['elType'] ) && in_array( $element['elType'], array( 'container', 'section' ), true ) ) {
			$data[ $i ] = mm_afbeelding_ruimte_markeer_element( $element );
		}
		break;
	}

	return $data;
}

/**
 * Markeert de afbeeldingswidgets in dit element en alles eronder.
 *
 * @param array $element Een element uit de Elementor-data.
 * @return array
 */
function mm_afbeelding_ruimte_markeer_element( $element ) {

	if ( isset( $element['elType'], $element['widgetType'] ) && 'widget' === $element['elType'] && in_array( $element['widgetType'], mm_afbeelding_ruimte_widgets(), true ) ) {
		$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		$klassen  = isset( $settings['_css_classes'] ) && is_string( $settings['_css_classes'] ) ? trim( $settings['_css_classes'] ) : '';
		if ( ! in_array( MM_AFBEELDING_RUIMTE_KLASSE, preg_split( '/\s+/', $klassen ), true ) ) {
			$settings['_css_classes'] = trim( $klassen . ' ' . MM_AFBEELDING_RUIMTE_KLASSE );
		}
		$element['settings'] = $settings;
	}

	if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
		foreach ( $element['elements'] as $i => $kind ) {
			if ( is_array( $kind ) ) {
				$element['elements'][ $i ] = mm_afbeelding_ruimte_markeer_element( $kind );
			}
		}
	}

	return $element;
}

add_filter( 'elementor/frontend/builder_content_data', 'mm_afbeelding_ruimte_markeer', 10, 2 );


/* -------------------------------------------------------------------------
 * De <img> aanpassen
 * ---------------------------------------------------------------------- */

function mm_afbeelding_ruimte_img( $html, $settings = array(), $image_size_key = '', $image_key = '' ) {

	if ( function_exists( 'mm_ig_elementor_img_attrs' ) ) {
		return $html;
	}

	if ( ! is_string( $html ) || false === stripos( $html, '<img' ) ) {
		return $html;
	}

	$voorrang = false;
	if ( is_array( $settings ) && isset( $settings['_css_classes'] ) && is_string( $settings['_css_classes'] ) ) {
		$voorrang = in_array( MM_AFBEELDING_RUIMTE_KLASSE, preg_split( '/\s+/', trim( $settings['_css_classes'] ) ), true );
	}

	return preg_replace_callback(
		'/<img\b[^>]*>/i',
		function ( $treffer ) use ( $voorrang ) {
			$tag = mm_afbeelding_ruimte_afmetingen( $treffer[0] );
			if ( $voorrang ) {
				$tag = mm_afbeelding_ruimte_voorrang( $tag );
			}
			return $tag;
		},
		$html
	);
}

add_filter( 'elementor/image_size/get_attachment_image_html', 'mm_afbeelding_ruimte_img', 10, 4 );

/**
 * Zet width en height op een <img> zonder width, als de src naar een
 * bestand in de eigen uploadsmap wijst.
 *
 * @param string $tag Eén <img>-tag.
 * @return string
 */
function mm_afbeelding_ruimte_afmetingen( $tag ) {

	if ( preg_match( '/\swidth\s*=/i', $tag ) ) {
		return $tag;
	}

	if ( ! preg_match( '/\ssrc\s*=\s*(["\'])(.*?)\1/i', $tag, $src ) ) {
		return $tag;
	}

	$maat = mm_afbeelding_ruimte_maat( html_entity_decode( $src[2], ENT_QUOTES ) );
	if ( ! $maat ) {
		return $tag;
	}

	$extra = ' width="' . (int) $maat[0] . '"';
	if ( ! preg_match( '/\sheight\s*=/i', $tag ) ) {
		$extra .= ' height="' . (int) $maat[1] . '"';
	}

	return preg_replace( '/^<img\b/i', '<img' . $extra, $tag, 1 );
}

/**
 * Breedte en hoogte van een afbeelding in de eigen uploadsmap, of null.
 * Per pad onthouden binnen dit verzoek, ook als het niets opleverde.
 *
 * @param string $url De src van de <img>.
 * @return int[]|null Array met breedte en hoogte.
 */
function mm_afbeelding_ruimte_maat( $url ) {

	static $cache = array();

	$pad = mm_afbeelding_ruimte_pad( $url );
	if ( '' === $pad ) {
		return null;
	}

	if ( array_key_exists( $pad, $cache ) ) {
		return $cache[ $pad ];
	}

	$cache[ $pad ] = null;

	if ( ! is_file( $pad ) || ! is_readable( $pad ) ) {
		return null;
	}

	// wp_getimagesize onderdrukt de waarschuwingen van getimagesize bij een
	// kapot bestand en kent ook WebP en AVIF.
	$info = function_exists( 'wp_getimagesize' ) ? wp_getimagesize( $pad ) : @getimagesize( $pad ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( ! is_array( $info ) || empty( $info[0] ) || empty( $info[1] ) ) {
		return null;
	}

	$cache[ $pad ] = array( (int) $info[0], (int) $info[1] );

	return $cache[ $pad ];
}

/**
 * Zet een url uit de eigen uploadsmap om naar een pad op schijf. Externe
 * url's, SVG's en paden met ../ geven een lege string.
 *
 * @param string $url
 * @return string
 */
function mm_afbeelding_ruimte_pad( $url ) {

	if ( ! is_string( $url ) || '' === $url || 0 === strpos( $url, 'data:' ) ) {
		return '';
	}

	$uploads = wp_get_upload_dir();
	if ( ! empty( $uploads['error'] ) || empty( $uploads['baseurl'] ) || empty( $uploads['basedir'] ) ) {
		return '';
	}

	// Zonder schema vergelijken: de pagina kan via https komen terwijl de
	// uploads-url nog http zegt, of andersom, en src kan //host/... zijn.
	$basis = preg_replace( '#^https?:#i', '', untrailingslashit( $uploads['baseurl'] ) ) . '/';
	$src   = preg_replace( '#^https?:#i', '', preg_replace( '/[?#].*$/s', '', $url ) );

	if ( 0 !== stripos( $src, $basis ) ) {
		return '';
	}

	$relatief = rawurldecode( substr( $src, strlen( $basis ) ) );

	if ( '' === $relatief || false !== strpos( $relatief, '..' ) ) {
		return '';
	}

	$ext = strtolower( pathinfo( $relatief, PATHINFO_EXTENSION ) );
	if ( in_array( $ext, array( 'svg', 'svgz' ), true ) ) {
		return '';
	}

	return untrailingslashit( $uploads['basedir'] ) . '/' . $relatief;
}

/**
 * Laat een <img> direct laden: loading="eager" en de class skip-lazy voor
 * EWWW. Een bestaande loading gaat eraf. Zonder loading zet WordPress er bij
 * wp_filter_content_tags later zelf weer lazy op.
 *
 * @param string $tag Eén <img>-tag.
 * @return string
 */
function mm_afbeelding_ruimte_voorrang( $tag ) {

	$tag = preg_replace( '/\sloading\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>\/]+)/i', '', $tag );
	$tag = preg_replace( '/^<img\b/i', '<img loading="eager"', $tag, 1 );

	if ( preg_match( '/\sclass\s*=\s*(["\'])(.*?)\1/i', $tag, $klasse ) ) {
		if ( ! in_array( 'skip-lazy', preg_split( '/\s+/', trim( $klasse[2] ) ), true ) ) {
			$nieuw = ' class=' . $klasse[1] . trim( $klasse[2] . ' skip-lazy' ) . $klasse[1];
			$tag   = str_replace( $klasse[0], $nieuw, $tag );
		}
	} else {
		$tag = preg_replace( '/^<img\b/i', '<img class="skip-lazy"', $tag, 1 );
	}

	return $tag;
}
