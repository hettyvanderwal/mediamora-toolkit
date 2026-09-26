<?php
/**
 * Mediamora Toolkit, module: Preview-link
 * Overgenomen uit mm-preview.php 1.3. Wordt alleen geladen als de module aanstaat.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MM_PREVIEW_PARAM', 'bekijk' );
define( 'MM_PREVIEW_COOKIE', 'mm_preview' );
define( 'MM_PREVIEW_DAYS', 180 );

/**
 * De sleutel van deze site.
 * Wordt bij de eerste keer automatisch aangemaakt en opgeslagen als optie.
 * Overschrijven kan met define( 'MM_PREVIEW_KEY', '...' ); in wp-config.php.
 * Sleutel wijzigen = alle bestaande links en cookies vervallen direct.
 */
function mm_preview_key() {
	if ( defined( 'MM_PREVIEW_KEY' ) && MM_PREVIEW_KEY ) {
		return MM_PREVIEW_KEY;
	}
	$key = get_option( 'mm_preview_key' );
	if ( ! $key && function_exists( 'wp_generate_password' ) ) {
		$key = wp_generate_password( 24, false );
		update_option( 'mm_preview_key', $key, false );
	}
	return (string) $key;
}

function mm_preview_cookie_value() {
	return hash_hmac( 'sha256', 'mm-preview', mm_preview_key() );
}

function mm_preview_url() {
	return add_query_arg( MM_PREVIEW_PARAM, mm_preview_key(), home_url( '/' ) );
}

/**
 * Preview-link voor de pagina waar je op dat moment staat.
 * In wp-admin valt hij terug op de homepage.
 */
function mm_preview_current_url() {
	if ( is_admin() || empty( $_SERVER['REQUEST_URI'] ) ) {
		return mm_preview_url();
	}
	$base    = wp_parse_url( home_url() );
	$host    = isset( $base['host'] ) ? $base['host'] : '';
	$port    = isset( $base['port'] ) ? ':' . $base['port'] : '';
	if ( ! $host ) {
		return mm_preview_url();
	}
	$current = ( is_ssl() ? 'https://' : 'http://' ) . $host . $port . wp_unslash( $_SERVER['REQUEST_URI'] );
	$current = remove_query_arg( MM_PREVIEW_PARAM, $current );
	return esc_url_raw( add_query_arg( MM_PREVIEW_PARAM, mm_preview_key(), $current ) );
}

function mm_preview_has_cookie() {
	if ( empty( $_COOKIE[ MM_PREVIEW_COOKIE ] ) || ! mm_preview_key() ) {
		return false;
	}
	return hash_equals( mm_preview_cookie_value(), (string) $_COOKIE[ MM_PREVIEW_COOKIE ] );
}

/**
 * Klik op de link: cookie zetten en de sleutel uit de adresbalk halen.
 */
add_action( 'init', 'mm_preview_maybe_set_cookie', 1 );
function mm_preview_maybe_set_cookie() {
	if ( is_admin() || empty( $_GET[ MM_PREVIEW_PARAM ] ) ) {
		return;
	}
	$given = (string) wp_unslash( $_GET[ MM_PREVIEW_PARAM ] );
	if ( ! mm_preview_key() || ! hash_equals( mm_preview_key(), $given ) ) {
		return;
	}
	setcookie( MM_PREVIEW_COOKIE, mm_preview_cookie_value(), array(
		'expires'  => time() + ( MM_PREVIEW_DAYS * DAY_IN_SECONDS ),
		'path'     => COOKIEPATH,
		'domain'   => COOKIE_DOMAIN,
		'secure'   => is_ssl(),
		'httponly' => true,
		'samesite' => 'Lax',
	) );
	wp_safe_redirect( remove_query_arg( MM_PREVIEW_PARAM ) );
	exit;
}

/**
 * Met geldige cookie: onderhoudsmodus van Elementor overslaan op de voorkant.
 */
add_filter( 'pre_option_elementor_maintenance_mode_mode', 'mm_preview_disable_maintenance' );
function mm_preview_disable_maintenance( $value ) {
	if ( is_admin() || ! mm_preview_has_cookie() ) {
		return $value;
	}
	return '';
}

/**
 * Met geldige cookie: nooit cachen en nooit indexeren.
 *
 * Op LiteSpeed komt dit te laat voor een pagina die al in de cache staat:
 * die wordt geserveerd voordat PHP draait. Daarvoor zet de toolkit zelf een
 * blok in .htaccess, zie "Preview-link: LiteSpeed-cache overslaan" in
 * mediamora-toolkit.php.
 */
add_action( 'init', 'mm_preview_protect', 2 );
function mm_preview_protect() {
	if ( is_admin() || ! mm_preview_has_cookie() ) {
		return;
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	do_action( 'litespeed_control_set_nocache', 'mediamora preview' );
	add_filter( 'wp_robots', 'wp_robots_no_robots' );
}

/**
 * Preview-link in de adminbalk, alleen voor beheerders.
 * Klikken kopieert de link van de huidige pagina naar het klembord.
 */
add_action( 'admin_bar_menu', 'mm_preview_admin_bar', 100 );
function mm_preview_admin_bar( $bar ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$url = mm_preview_current_url();

	$js  = 'var u=' . wp_json_encode( $url ) . ';';
	$js .= 'var l=this.querySelector(".mm-preview-label");';
	$js .= 'var ok=function(){if(l){l.textContent="Gekopieerd";setTimeout(function(){l.textContent="Preview-link";},2000);}};';
	$js .= 'if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(u).then(ok);}';
	$js .= 'else{var t=document.createElement("textarea");t.value=u;document.body.appendChild(t);t.select();document.execCommand("copy");document.body.removeChild(t);ok();}';
	$js .= 'return false;';

	$bar->add_node( array(
		'id'    => 'mm-preview',
		'title' => '<span class="ab-icon dashicons dashicons-clipboard" style="top:2px;"></span><span class="mm-preview-label">Preview-link</span>',
		'href'  => $url,
		'meta'  => array(
			'onclick' => $js,
			'title'   => 'Kopieer de preview-link van deze pagina',
		),
	) );
}
