<?php
/**
 * Mediamora Toolkit, module: Hero-preload
 * Overgenomen uit mediamora-hero-preload.php 1.2. Wordt alleen geladen als de module aanstaat.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bepaalt waar de hero van de opgevraagde pagina in staat.
 *
 * Op een losse pagina of post is dat de eigen Elementor-data. Op een archief (winkel,
 * productcategorie, blog, zoekresultaten) staat de hero in het archieftemplate van de
 * Theme Builder. Welk template dat is, laten we Elementor Pro zelf bepalen via zijn
 * conditions manager, zodat het precies het template is dat Elementor op deze pagina
 * rendert. Product-archive-templates vallen ook onder de locatie archive.
 *
 * Een site kan de bron overschrijven met het filter mediamora_hero_preload_bron. Dat
 * krijgt de gevonden bron mee (of null) en moet een array met id en data teruggeven.
 *
 * @return array|null Array met id (post- of template-ID) en data (JSON uit _elementor_data), of null.
 */
function mm_hero_preload_bron() {

	$bron = null;

	if ( is_singular() ) {
		$post_id = get_queried_object_id();
		if ( $post_id ) {
			$bron = array(
				'id'   => $post_id,
				'data' => get_post_meta( $post_id, '_elementor_data', true ),
			);
		}
	} elseif ( is_archive() || is_home() || is_search() ) {
		$template_id = mm_hero_preload_archieftemplate();
		if ( $template_id ) {
			$bron = array(
				'id'   => $template_id,
				'data' => get_post_meta( $template_id, '_elementor_data', true ),
			);
		}
	} else {
		return null;
	}

	$bron = apply_filters( 'mediamora_hero_preload_bron', $bron );

	if ( ! is_array( $bron ) || empty( $bron['id'] ) || ! isset( $bron['data'] ) ) {
		return null;
	}

	if ( is_array( $bron['data'] ) ) {
		$bron['data'] = wp_json_encode( $bron['data'] );
	}

	if ( ! is_string( $bron['data'] ) || '' === $bron['data'] ) {
		return null;
	}

	return array(
		'id'   => (int) $bron['id'],
		'data' => $bron['data'],
	);
}

/**
 * Geeft het ID van het archieftemplate dat Elementor Pro op deze pagina rendert, of 0.
 * Zonder Elementor Pro, zonder Theme Builder of zonder passend template: 0.
 */
function mm_hero_preload_archieftemplate() {

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

		// Array van template-ID naar document, al gesorteerd op prioriteit. De locatie
		// archive toont er maar één, dus de eerste is het template dat wordt gerenderd.
		$documenten = $conditions->get_documents_for_location( 'archive' );
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

/**
 * Zoekt in documentvolgorde (eerst de settings van een element, dan zijn kinderen) het
 * eerste element met een ingestelde background_background en geeft de settings daarvan.
 * Dat is de bovenste achtergrond op de pagina. Alleen background_background telt, niet
 * _background_background of background_overlay_background.
 *
 * @param array $elementen Elementen uit de gedecodeerde Elementor-data.
 * @return array|null Settings van het gevonden element, of null.
 */
function mm_hero_preload_eerste_achtergrond( $elementen ) {

	foreach ( $elementen as $element ) {
		if ( ! is_array( $element ) ) {
			continue;
		}

		if ( isset( $element['settings'] ) && is_array( $element['settings'] ) && ! empty( $element['settings']['background_background'] ) && is_string( $element['settings']['background_background'] ) ) {
			return $element['settings'];
		}

		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$gevonden = mm_hero_preload_eerste_achtergrond( $element['elements'] );
			if ( $gevonden ) {
				return $gevonden;
			}
		}
	}

	return null;
}

/**
 * Of de settings van een element een echte achtergrondafbeelding hebben: classic met een
 * dynamic tag of een vaste afbeelding met url, of slideshow met een dynamic tag of een
 * niet-lege galerij. Alleen een kleur, een verloop of een video telt niet.
 *
 * @param array $settings Settings van een element.
 * @return bool
 */
function mm_hero_preload_heeft_afbeelding( $settings ) {

	if ( empty( $settings['background_background'] ) || ! is_string( $settings['background_background'] ) ) {
		return false;
	}

	$soort = $settings['background_background'];

	if ( 'classic' === $soort ) {
		if ( ! empty( $settings['__dynamic__']['background_image'] ) ) {
			return true;
		}
		return isset( $settings['background_image']['url'] ) && is_string( $settings['background_image']['url'] ) && '' !== $settings['background_image']['url'];
	}

	if ( 'slideshow' === $soort ) {
		if ( ! empty( $settings['__dynamic__']['background_slideshow_gallery'] ) ) {
			return true;
		}
		return ! empty( $settings['background_slideshow_gallery'] ) && is_array( $settings['background_slideshow_gallery'] );
	}

	return false;
}

/**
 * Zoekt in documentvolgorde (eerst het element zelf, dan zijn kinderen) het eerste element
 * met een echte achtergrondafbeelding. Zie mm_hero_preload_heeft_afbeelding().
 *
 * @param array $elementen Elementen uit de gedecodeerde Elementor-data.
 * @return array|null Het gevonden element (met id en settings), of null.
 */
function mm_hero_preload_eerste_afbeelding( $elementen ) {

	foreach ( $elementen as $element ) {
		if ( ! is_array( $element ) ) {
			continue;
		}

		if ( isset( $element['settings'] ) && is_array( $element['settings'] ) && mm_hero_preload_heeft_afbeelding( $element['settings'] ) ) {
			return $element;
		}

		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$gevonden = mm_hero_preload_eerste_afbeelding( $element['elements'] );
			if ( $gevonden ) {
				return $gevonden;
			}
		}
	}

	return null;
}

/**
 * Bepaalt het element waarvan de achtergrond gepreload wordt.
 *
 * Eerst de hero-sectie: het bovenste top-level element waarin of waaronder een
 * background_background staat (zie mm_hero_preload_eerste_achtergrond()). Daarbinnen,
 * en alleen daarbinnen, het eerste element met een echte afbeelding. Een kaart met
 * alleen een kleur boven de foto in dezelfde sectie wordt dus overgeslagen. Staat er in
 * de hero-sectie geen afbeelding (een effen hero), dan null: niet doorzoeken naar een
 * volgende sectie, want dan krijgt een foto verder op de pagina voorrang.
 *
 * @param array $elementen Elementen uit de gedecodeerde Elementor-data.
 * @return array|null Het gekozen element (met id en settings), of null.
 */
function mm_hero_preload_hero_element( $elementen ) {

	foreach ( $elementen as $element ) {
		if ( ! is_array( $element ) ) {
			continue;
		}

		if ( mm_hero_preload_eerste_achtergrond( array( $element ) ) ) {
			return mm_hero_preload_eerste_afbeelding( array( $element ) );
		}
	}

	return null;
}

/**
 * Of een element een eigen tablet- of mobiele achtergrondafbeelding heeft: een niet-lege
 * url of een dynamic tag voor background_image_tablet of background_image_mobile.
 *
 * @param array $settings Settings van het gekozen element.
 * @return bool
 */
function mm_hero_preload_heeft_responsieve_afbeelding( $settings ) {

	foreach ( array( 'background_image_tablet', 'background_image_mobile' ) as $sleutel ) {
		if ( ! empty( $settings['__dynamic__'][ $sleutel ] ) ) {
			return true;
		}
		if ( isset( $settings[ $sleutel ]['url'] ) && is_string( $settings[ $sleutel ]['url'] ) && '' !== $settings[ $sleutel ]['url'] ) {
			return true;
		}
	}

	return false;
}

/**
 * Lost een dynamic tag van Elementor op, zoals die in settings['__dynamic__'] staat, en
 * geeft de waarde terug: bij een afbeelding een array met id en url, bij een galerij een
 * lijst van zulke arrays. Zonder Elementor of als het misgaat: null.
 *
 * @param string $tag De tag, bijvoorbeeld [elementor-tag id="..." name="..." settings="..."].
 * @return mixed|null
 */
function mm_hero_preload_dynamische_tag( $tag ) {

	if ( ! is_string( $tag ) || '' === $tag || ! class_exists( '\Elementor\Plugin' ) ) {
		return null;
	}

	try {
		$elementor = \Elementor\Plugin::$instance;
		if ( ! $elementor || empty( $elementor->dynamic_tags ) ) {
			return null;
		}

		$tags = $elementor->dynamic_tags;
		if ( ! method_exists( $tags, 'parse_tags_text' ) || ! method_exists( $tags, 'get_tag_data_content' ) ) {
			return null;
		}

		return $tags->parse_tags_text( $tag, array( 'returnType' => 'object' ), array( $tags, 'get_tag_data_content' ) );
	} catch ( \Throwable $e ) {
		return null;
	}
}

/**
 * Leest de eerste achtergrondafbeelding uit de Elementor-data van de opgevraagde pagina,
 * of op een archief uit het archieftemplate, en zet daar een preload voor in de head.
 * Geen vaste bestandsnaam, dus de preload beweegt mee zodra de afbeelding wordt vervangen.
 *
 * Uitschakelen kan met: add_filter( 'mediamora_hero_preload_actief', '__return_false' );
 */
add_action( 'wp_head', function () {

	if ( is_admin() ) {
		return;
	}

	if ( ! apply_filters( 'mediamora_hero_preload_actief', true ) ) {
		return;
	}

	$bron = mm_hero_preload_bron();
	if ( ! $bron ) {
		return;
	}

	$bron_id = $bron['id'];
	$data    = $bron['data'];

	// De hero kan een gewone achtergrondafbeelding zijn of een achtergrond-slideshow.
	// Bepalend is het type van het gekozen element, het eerste met een echte afbeelding
	// in de bovenste sectie met een achtergrond. Niet zomaar zoeken op background_image:
	// een container die ooit klassiek was en later een slideshow werd, houdt die oude
	// sleutel gewoon.
	$elementen = json_decode( $data, true );
	if ( ! is_array( $elementen ) ) {
		return;
	}

	$element = mm_hero_preload_hero_element( $elementen );
	if ( ! $element ) {
		return;
	}

	$settings = $element['settings'];

	// Heeft dit element een aparte tablet- of mobiele achtergrond, dan is niet te bepalen
	// welk bestand deze bezoeker krijgt. Dan liever niets preloaden dan het verkeerde
	// bestand binnenhalen. Zo'n sleutel op een ander element telt niet mee.
	if ( mm_hero_preload_heeft_responsieve_afbeelding( $settings ) ) {
		return;
	}

	$soort = $settings['background_background'];
	$url   = '';

	if ( 'slideshow' === $soort ) {
		// De eerste dia is wat de bezoeker als eerste ziet. Staat er een dynamic tag voor de
		// galerij, dan gebruikt Elementor bij het renderen altijd de tag, ook als er nog een
		// oude vaste galerij in de data staat. Dus hier ook: tag gaat voor, zonder terugval.
		if ( ! empty( $settings['__dynamic__']['background_slideshow_gallery'] ) ) {
			$dias = mm_hero_preload_dynamische_tag( $settings['__dynamic__']['background_slideshow_gallery'] );
		} else {
			$dias = isset( $settings['background_slideshow_gallery'] ) ? $settings['background_slideshow_gallery'] : array();
		}
		if ( is_array( $dias ) && isset( $dias[0]['url'] ) && is_string( $dias[0]['url'] ) ) {
			$url = $dias[0]['url'];
		}
	} elseif ( 'classic' === $soort ) {
		// Een dynamic tag, bijvoorbeeld de categorieafbeelding of de uitgelichte afbeelding,
		// gaat voor. Elementor gebruikt bij het renderen altijd de tag, ook als er nog een
		// oude vaste afbeelding in de data staat. Levert de tag niets bruikbaars op, dan geen
		// preload en geen terugval op die vaste afbeelding.
		if ( ! empty( $settings['__dynamic__']['background_image'] ) ) {
			$beeld = mm_hero_preload_dynamische_tag( $settings['__dynamic__']['background_image'] );
		} else {
			$beeld = isset( $settings['background_image'] ) ? $settings['background_image'] : array();
		}
		if ( is_array( $beeld ) && isset( $beeld['url'] ) && is_string( $beeld['url'] ) ) {
			$url = $beeld['url'];
		}
	}

	if ( '' === $url ) {
		return;
	}

	$ext = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'avif' ), true ) ) {
		return;
	}

	printf(
		'<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n",
		esc_url( $url )
	);

	// Een achtergrond-slideshow wordt door Elementor's JavaScript opgebouwd: in de HTML
	// staat alleen een lege container met de instellingen in een data-attribuut. De
	// browser kan de hero dus pas tekenen als alle JS is gedraaid, en dat kost op mobiel
	// seconden. Daarom zetten we de eerste dia ook als gewone CSS-achtergrond op die
	// container. De browser schildert hem dan zodra de CSS binnen is, met de afbeelding
	// die hierboven al is gepreload. Swiper legt zijn eigen dia's er daarna overheen.
	//
	// Alleen bij een slideshow. Bij een klassieke achtergrond zet Elementor deze regel
	// zelf al in de pagina-CSS.
	if ( 'slideshow' !== $soort ) {
		return;
	}

	// De CSS komt op het gekozen element, dat ook genest kan zijn. Elementor zet het ID
	// van de bron als class op de wrapper (.elementor-{ID}), op een archief dus het
	// template-ID.
	if ( empty( $element['id'] ) || ! is_string( $element['id'] ) || ! preg_match( '#^[0-9a-zA-Z]+$#', $element['id'] ) ) {
		return;
	}

	printf(
		'<style id="mediamora-hero-verf">.elementor-%1$d .elementor-element.elementor-element-%2$s{background-image:url("%3$s");background-size:cover;background-position:center center;background-repeat:no-repeat;}</style>' . "\n",
		(int) $bron_id,
		esc_attr( $element['id'] ),
		esc_url( $url )
	);

}, 1 );
