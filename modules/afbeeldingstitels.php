<?php
/**
 * Mediamora Toolkit, module: Afbeeldingstitels verbergen
 * Vervangt de tijdelijke code in functions.php van het child theme (drentz.nl).
 * Wordt alleen geladen als de module aanstaat.
 *
 * Met een eigen uitsnede (image_size custom, bijvoorbeeld 1000x1333 of
 * 1000x1000) bouwt Elementor de <img> zelf op in
 * Group_Control_Image_Size::get_attachment_image_html() en zet er
 * title="<titel uit de mediabibliotheek>" op. Bezoekers zien bij hover dan
 * de bestandsnaam als tooltip. Dat geldt voor elke widget die via die functie
 * loopt: Afbeelding, Afbeeldingsvak, Testimonial en de afbeelding over een
 * Video, en in Elementor Pro Call to action, Flip box, Sitelogo, Portfolio,
 * Hotspot, de uitgelichte afbeelding en de skins Klassiek en Kaarten van
 * Berichten. Met een gewone maat (thumbnail, large, full) gaat het via
 * wp_get_attachment_image(), en die zet geen title.
 *
 * Alleen het title-attribuut van de <img> gaat eraf; alt, class, loading,
 * data-attributen en de rest blijven staan. data-title en dergelijke blijven
 * ook staan, want voor title moet witruimte staan.
 *
 * De functienaam staat vast: op drentz.nl staat een tijdelijke versie met
 * dezelfde naam en dezelfde function_exists-check in functions.php. Die laadt
 * na de plugins en slaat zichzelf dus over zolang deze module aan staat.
 *
 * Elementor bewaart de HTML van widgets in _elementor_element_cache. Het
 * legen daarvan bij aan- en uitzetten staat in het hoofdbestand, zie
 * mm_toolkit_afbeeldingstitels_herstel(): een module die uit staat laadt niet.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'mm_verberg_afbeeldingstitel' ) ) {
	function mm_verberg_afbeeldingstitel( $html ) {

		if ( ! is_string( $html ) || false === stripos( $html, 'title=' ) ) {
			return $html;
		}

		return preg_replace( '/(<img\b[^>]*?)\s+title="[^"]*"/i', '$1', $html );
	}
}

// Buiten de check: mocht de functie al ergens anders vandaan komen, dan hangt
// hij hier toch aan het filter. WordPress voegt dezelfde callback op dezelfde
// prioriteit geen tweede keer toe.
add_filter( 'elementor/image_size/get_attachment_image_html', 'mm_verberg_afbeeldingstitel' );
