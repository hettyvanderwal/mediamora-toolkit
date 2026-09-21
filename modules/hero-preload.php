<?php
/**
 * Mediamora Toolkit, module: Hero-preload
 * Overgenomen uit mediamora-hero-preload.php 1.2. Wordt alleen geladen als de module aanstaat.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Leest de eerste achtergrondafbeelding uit de Elementor-data van de opgevraagde pagina
 * en zet daar een preload voor in de head. Geen vaste bestandsnaam, dus de preload
 * beweegt mee zodra de afbeelding wordt vervangen.
 *
 * Uitschakelen kan met: add_filter( 'mediamora_hero_preload_actief', '__return_false' );
 */
add_action( 'wp_head', function () {

	if ( is_admin() || ! is_singular() ) {
		return;
	}

	if ( ! apply_filters( 'mediamora_hero_preload_actief', true ) ) {
		return;
	}

	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return;
	}

	$data = get_post_meta( $post_id, '_elementor_data', true );
	if ( ! is_string( $data ) || '' === $data ) {
		return;
	}

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
	if ( ! preg_match( '#"background_background":"([a-z]+)"#', $data, $type, PREG_OFFSET_CAPTURE ) ) {
		return;
	}

	$soort  = $type[1][0];
	$vanaf  = $type[0][1];
	$restje = substr( $data, $vanaf );
	$url    = '';

	if ( 'slideshow' === $soort ) {
		// De eerste dia is wat de bezoeker als eerste ziet.
		if ( preg_match( '#"background_slideshow_gallery":\[\{[^}]*?"url":"([^"]+)"#', $restje, $treffer ) ) {
			$url = stripslashes( $treffer[1] );
		}
	} elseif ( 'classic' === $soort ) {
		if ( preg_match( '#"background_image":\{[^}]*"url":"([^"]+)"#', $restje, $treffer ) ) {
			$url = stripslashes( $treffer[1] );
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
	if ( ! preg_match( '#^\[\{"id":"([0-9a-zA-Z]+)"#', $data, $container ) ) {
		return;
	}

	printf(
		'<style id="mediamora-hero-verf">.elementor-%1$d .elementor-element.elementor-element-%2$s{background-image:url("%3$s");background-size:cover;background-position:center center;background-repeat:no-repeat;}</style>' . "\n",
		(int) $post_id,
		esc_attr( $container[1] ),
		esc_url( $url )
	);

}, 1 );
