<?php
/**
 * Mediamora Toolkit, module: QUIC.cloud en kritieke CSS
 * Overgenomen uit de testcode in functions.php (Falcon-i, sanbao.be).
 * Wordt alleen geladen als de module aanstaat.
 *
 * Per site aan te zetten wanneer de kritieke CSS (CCSS) van LiteSpeed via
 * QUIC.cloud wordt ingericht. Drie onderdelen:
 *
 * 1. REST-uitzondering: "REST API uitschakelen voor niet-ingelogde bezoekers"
 *    in ASE blokkeert het terugbellen van QUIC.cloud. De routes van LiteSpeed
 *    mogen er weer door.
 * 2. Allowlist-bewaking: ontbreekt #elementor-device-mode in de CCSS-allowlist,
 *    dan een melding met een knop om het toe te voegen.
 * 3. De noscript-regel van EWWW Lazy Load uit de kritieke CSS halen.
 *
 * Zonder LiteSpeed Cache of ASE doet de module niets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * 1. REST-uitzondering voor QUIC.cloud
 *
 * Gefilterd op de route en niet op REQUEST_URI: QUIC.cloud roept aan via
 * ?rest_route=/litespeed/... Alleen ingrijpen als er al een WP_Error is,
 * dus als ASE (of iets anders) het verzoek al heeft geweigerd.
 *
 * Werkt naast REST-gebruikers afschermen: die module zit op rest_pre_dispatch
 * en alleen op /wp/v2/users, deze alleen op /litespeed/.
 * ---------------------------------------------------------------------- */

function mm_quic_rest_uitzondering( $result ) {
	$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
	if ( is_wp_error( $result ) && strpos( $route, '/litespeed/' ) === 0 ) {
		return null;
	}
	return $result;
}

add_filter( 'rest_authentication_errors', 'mm_quic_rest_uitzondering', 999 );


/* -------------------------------------------------------------------------
 * 2. Allowlist-bewaking
 *
 * Elementor leest via het element #elementor-device-mode of de bezoeker op
 * mobiel, tablet of desktop zit. Met CSS asynchroon laden (optm-css_async)
 * moet die regel in de kritieke CSS staan, anders leest Elementor bij het
 * laden de verkeerde schermmaat. Staat hij niet in de allowlist
 * (optm-ccss_whitelist), dan komt er een melding voor beheerders met een knop.
 * Er wordt niets gewijzigd zonder klik.
 * ---------------------------------------------------------------------- */

const MM_QUIC_DEVICE_MODE = '#elementor-device-mode';

/**
 * Draait LiteSpeed Cache, met de klasse die we nodig hebben om op te slaan?
 */
function mm_quic_litespeed_actief() {
	return defined( 'LSCWP_V' ) && class_exists( '\LiteSpeed\Conf' ) && method_exists( '\LiteSpeed\Conf', 'cls' );
}

/**
 * Een instelling van LiteSpeed uitlezen.
 *
 * @param string $sleutel Bijvoorbeeld 'optm-css_async'.
 * @return mixed
 */
function mm_quic_litespeed_waarde( $sleutel ) {
	$conf = \LiteSpeed\Conf::cls();
	if ( method_exists( $conf, 'conf' ) ) {
		return $conf->conf( $sleutel );
	}
	return get_option( 'litespeed.conf.' . $sleutel );
}

/**
 * De CCSS-allowlist als platte lijst. De optie komt soms terug als array
 * met één JSON-string, soms als gewone array of als tekst met regels.
 *
 * @return string[]
 */
function mm_quic_allowlist() {

	$waarde = mm_quic_litespeed_waarde( 'optm-ccss_whitelist' );
	$lijst  = array();

	foreach ( (array) $waarde as $regel ) {
		if ( ! is_string( $regel ) ) {
			continue;
		}
		$regel = trim( $regel );
		if ( '' !== $regel && '[' === $regel[0] ) {
			$json = json_decode( $regel, true );
			if ( is_array( $json ) ) {
				foreach ( $json as $deel ) {
					if ( is_string( $deel ) ) {
						$lijst[] = trim( $deel );
					}
				}
				continue;
			}
		}
		foreach ( preg_split( '/\r\n|\r|\n/', $regel ) as $deel ) {
			$lijst[] = trim( $deel );
		}
	}

	return array_values( array_unique( array_filter( $lijst, 'strlen' ) ) );
}

/**
 * Ontbreekt #elementor-device-mode terwijl CSS asynchroon laadt?
 */
function mm_quic_device_mode_ontbreekt() {

	if ( ! mm_quic_litespeed_actief() ) {
		return false;
	}

	if ( ! mm_quic_litespeed_waarde( 'optm-css_async' ) ) {
		return false;
	}

	return ! in_array( MM_QUIC_DEVICE_MODE, mm_quic_allowlist(), true );
}

add_action( 'admin_notices', 'mm_quic_allowlist_melding' );

function mm_quic_allowlist_melding() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_GET['mm_quic_ccss'] ) ) {
		if ( 'ok' === $_GET['mm_quic_ccss'] ) {
			echo '<div class="notice notice-success is-dismissible"><p><strong>Mediamora Toolkit:</strong> <code>' . esc_html( MM_QUIC_DEVICE_MODE ) . '</code> staat nu in de CCSS-allowlist van LiteSpeed. Bestaande kritieke CSS wordt pas bijgewerkt als LiteSpeed hem opnieuw laat maken.</p></div>';
		} else {
			echo '<div class="notice notice-error is-dismissible"><p><strong>Mediamora Toolkit:</strong> kon <code>' . esc_html( MM_QUIC_DEVICE_MODE ) . '</code> niet aan de CCSS-allowlist toevoegen. Voeg het zelf toe onder LiteSpeed Cache &gt; Paginaoptimalisatie &gt; CSS-instellingen.</p></div>';
		}
	}

	if ( ! mm_quic_device_mode_ontbreekt() ) {
		return;
	}

	echo '<div class="notice notice-warning"><p><strong>Mediamora Toolkit:</strong> LiteSpeed laadt de CSS asynchroon met kritieke CSS, maar <code>' . esc_html( MM_QUIC_DEVICE_MODE ) . '</code> staat niet in de CCSS-allowlist. Elementor leest via dat element of de bezoeker op mobiel, tablet of desktop zit. Ontbreekt de regel in de kritieke CSS, dan leest Elementor bij het laden de verkeerde schermmaat. De bestaande regels in de allowlist blijven staan.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0 0 8px;">';
	echo '<input type="hidden" name="action" value="mm_quic_ccss_toevoegen">';
	wp_nonce_field( 'mm_quic_ccss_toevoegen' );
	echo '<button type="submit" class="button button-primary">Toevoegen</button>';
	echo '</form></div>';
}

add_action( 'admin_post_mm_quic_ccss_toevoegen', 'mm_quic_ccss_toevoegen' );

function mm_quic_ccss_toevoegen() {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Geen toegang.' );
	}
	check_admin_referer( 'mm_quic_ccss_toevoegen' );

	$uitkomst = 'mislukt';

	if ( mm_quic_litespeed_actief() ) {
		$lijst = mm_quic_allowlist();
		if ( ! in_array( MM_QUIC_DEVICE_MODE, $lijst, true ) ) {
			$lijst[] = MM_QUIC_DEVICE_MODE;
			$conf    = \LiteSpeed\Conf::cls();
			if ( method_exists( $conf, 'update_confs' ) ) {
				$conf->update_confs( array( 'optm-ccss_whitelist' => $lijst ) );
			}
		}
		if ( in_array( MM_QUIC_DEVICE_MODE, mm_quic_allowlist(), true ) ) {
			$uitkomst = 'ok';
		}
	}

	$terug = wp_get_referer();
	$terug = $terug ? remove_query_arg( 'mm_quic_ccss', $terug ) : admin_url();

	wp_safe_redirect( add_query_arg( 'mm_quic_ccss', $uitkomst, $terug ) );
	exit;
}


/* -------------------------------------------------------------------------
 * 3. Noscript-regel van EWWW uit de kritieke CSS
 *
 * EWWW Lazy Load zet <noscript><style>.lazyload[data-src]{display:none
 * !important;}</style></noscript> in de pagina. QUIC.cloud neemt die regel
 * op in de CCSS, waardoor alle lazy-load-afbeeldingen onzichtbaar blijven en
 * nooit laden. Alleen in een head met kritieke CSS (litespeed-ccss).
 *
 * Staat de testversie nog in functions.php (mm_ccss_zonder_noscript), dan
 * doet deze niets. Dat wordt pas bij het filteren gecontroleerd, want
 * functions.php laadt na de plugins.
 * ---------------------------------------------------------------------- */

function mm_quic_ccss_zonder_lazyload( $head ) {
	if ( function_exists( 'mm_ccss_zonder_noscript' ) ) {
		return $head;
	}
	if ( ! is_string( $head ) || false === strpos( $head, 'litespeed-ccss' ) ) {
		return $head;
	}
	return preg_replace( '/\.lazyload\[data-src\]\s*\{\s*display\s*:\s*none\s*!important\s*;?\s*\}/i', '', $head );
}

add_filter( 'litespeed_optm_html_head', 'mm_quic_ccss_zonder_lazyload' );
