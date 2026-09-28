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

	// Staat er een aparte tablet- of mobiele achtergrond ingesteld, dan is niet te
	// bepalen welk bestand deze bezoeker krijgt. Dan liever niets preloaden dan het
	// verkeerde bestand binnenhalen.
	if ( false !== strpos( $data, 'background_image_mobile' ) || false !== strpos( $data, 'background_image_tablet' ) ) {
		return;
	}

	// De hero kan een gewone achtergrondafbeelding zijn of een achtergrond-slideshow.
	// Bepalend is het type van de EERSTE container in de Elementor-data, want dat is de
	// bovenste sectie op de pagina. Niet zomaar zoeken op background_image: een container
	// die ooit klassiek was en later een slideshow werd, houdt die oude sleutel gewoon.
	$elementen = json_decode( $data, true );
	if ( ! is_array( $elementen ) ) {
		return;
	}

	$settings = mm_hero_preload_eerste_achtergrond( $elementen );
	if ( ! $settings ) {
		return;
	}

	$soort = $settings['background_background'];
	$url   = '';

	if ( 'slideshow' === $soort ) {
		// De eerste dia is wat de bezoeker als eerste ziet.
		$dias = isset( $settings['background_slideshow_gallery'] ) ? $settings['background_slideshow_gallery'] : array();
		if ( ( ! is_array( $dias ) || empty( $dias[0]['url'] ) ) && ! empty( $settings['__dynamic__']['background_slideshow_gallery'] ) ) {
			$dias = mm_hero_preload_dynamische_tag( $settings['__dynamic__']['background_slideshow_gallery'] );
		}
		if ( is_array( $dias ) && isset( $dias[0]['url'] ) && is_string( $dias[0]['url'] ) ) {
			$url = $dias[0]['url'];
		}
	} elseif ( 'classic' === $soort ) {
		$beeld = isset( $settings['background_image'] ) ? $settings['background_image'] : array();
		if ( ( ! is_array( $beeld ) || empty( $beeld['url'] ) ) && ! empty( $settings['__dynamic__']['background_image'] ) ) {
			// Bijvoorbeeld de categorieafbeelding of de uitgelichte afbeelding. De url in de
			// data is dan leeg; Elementor vult hem pas bij het renderen via de tag.
			$beeld = mm_hero_preload_dynamische_tag( $settings['__dynamic__']['background_image'] );
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

	// Het eerste element in de Elementor-data is de bovenste container op de pagina.
	// Elementor zet het ID van de bron als class op de wrapper (.elementor-{ID}), op een
	// archief dus het template-ID.
	if ( ! preg_match( '#^\[\{"id":"([0-9a-zA-Z]+)"#', $data, $container ) ) {
		return;
	}

	printf(
		'<style id="mediamora-hero-verf">.elementor-%1$d .elementor-element.elementor-element-%2$s{background-image:url("%3$s");background-size:cover;background-position:center center;background-repeat:no-repeat;}</style>' . "\n",
		(int) $bron_id,
		esc_attr( $container[1] ),
		esc_url( $url )
	);

}, 1 );
