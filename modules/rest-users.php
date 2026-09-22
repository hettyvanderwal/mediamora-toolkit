<?php
/**
 * Mediamora Toolkit, module: REST-gebruikers afschermen
 * Overgenomen uit mediamora-rest-users.php. Wordt alleen geladen als de module aanstaat.
 *
 * Sluit /wp/v2/users af voor niet-ingelogde bezoekers. Dat endpoint geeft per
 * gebruiker de slug terug, en die is afgeleid van de inlognaam.
 *
 * Bewust niet de hele REST API: op een webshop lopen de Mollie- en
 * MyParcel-webhooks en de WooCommerce Store API ook via /wp-json/, en die
 * komen altijd niet-ingelogd binnen. Ingelogde gebruikers (Elementor-editor,
 * Novamira via het application password) houden gewoon toegang.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mm_rest_users_afschermen( $resultaat, $server, $verzoek ) {

	if ( is_user_logged_in() ) {
		return $resultaat;
	}

	if ( preg_match( '#^/wp/v2/users#i', $verzoek->get_route() ) ) {
		return new WP_Error( 'rest_forbidden', 'Niet toegestaan.', array( 'status' => 401 ) );
	}

	return $resultaat;
}

add_filter( 'rest_pre_dispatch', 'mm_rest_users_afschermen', 10, 3 );
