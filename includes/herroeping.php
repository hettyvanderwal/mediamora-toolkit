<?php
/**
 * Mediamora Toolkit, Herroepingsrecht bij afrekenen: omzetten van de
 * instellingen van 1.6.0 en 1.7.0.
 *
 * Staat bewust hier en niet in de module: een module die uit staat laadt
 * niet, en dan zou het omzetten wachten tot iemand hem aanzet. Nu gebeurt
 * het meteen na de update, ook als niemand de instellingenpagina opent.
 *
 * 1.6.0 kende één soort (digitale content) met een bereik "alle" of
 * "gemarkeerd". Vanaf 1.8.0 zijn er twee soorten, digitaal en dienst, en
 * bepaalt per product een keuzeveld, een categorie of de standaardsoort
 * welke soort geldt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MM_HERROEPING_OPTIE = 'mm_withdrawal_waiver_settings';

/** Versievlag in de optie. Ontbreekt die, dan is het nog de optie van 1.6.0. */
const MM_HERROEPING_OPTIE_VERSIE = 2;

/** Metakey op het product: '', 'digitaal', 'dienst' of 'geen'. */
const MM_HERROEPING_SOORT_META = '_mm_withdrawal_type';

/**
 * Het productvinkje van 1.6.0 ('yes'). Wordt na het omzetten niet meer
 * gebruikt, maar blijft staan tot de plugin wordt verwijderd.
 */
const MM_HERROEPING_OUD_PRODUCT_META = '_mm_withdrawal_waiver_applies';

// Vóór de hooks van de module, die ook op init staan.
add_action( 'init', 'mm_herroeping_omzetten', -1 );

/**
 * Zet de optie van 1.6.0 eenmalig om. Doet niets als er geen optie is (dan
 * gelden de standaardwaardes, gelijk aan "alle" van 1.6.0) of als de
 * versievlag er al staat. Wordt ook aangeroepen door de module voordat die
 * de instellingen leest, zodat de checkout nooit met een niet-omgezette
 * optie draait.
 */
function mm_herroeping_omzetten() {

	$oud = get_option( MM_HERROEPING_OPTIE, false );
	if ( ! is_array( $oud ) || isset( $oud['versie'] ) ) {
		return;
	}

	$gemarkeerd = isset( $oud['bereik'] ) && 'gemarkeerd' === $oud['bereik'];

	$nieuw = array(
		'versie'               => MM_HERROEPING_OPTIE_VERSIE,
		'standaardsoort'       => $gemarkeerd ? 'geen' : 'digitaal',
		'categorieen_digitaal' => array(),
		'categorieen_dienst'   => array(),
	);

	if ( $gemarkeerd ) {
		$nieuw['categorieen_digitaal'] = isset( $oud['categorieen'] )
			? array_values( array_filter( array_map( 'absint', (array) $oud['categorieen'] ) ) )
			: array();

		global $wpdb;
		$producten = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = 'yes'",
				MM_HERROEPING_OUD_PRODUCT_META
			)
		);
		foreach ( (array) $producten as $product_id ) {
			// Uniek: een keuze die er al staat blijft staan.
			add_post_meta( (int) $product_id, MM_HERROEPING_SOORT_META, 'digitaal', true );
		}
	}

	foreach ( array( 'vinkje', 'fout', 'label' ) as $veld ) {
		$nieuw[ 'digitaal_' . $veld ] = isset( $oud[ 'tekst_' . $veld ] ) ? (string) $oud[ 'tekst_' . $veld ] : '';
	}

	update_option( MM_HERROEPING_OPTIE, $nieuw, false );
}
