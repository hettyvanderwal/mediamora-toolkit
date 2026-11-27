<?php
/**
 * Mediamora Toolkit, status meegeven met de sync van MainWP.
 *
 * Staat niet in een module, want het dashboard moet ook zien dat de site
 * geen onderhoudsklant is of dat de Formuliermonitor uit staat. De modules
 * laden dan niet.
 *
 * Nagekeken in MainWP Child 6.4.2 en MainWP Dashboard 6.4.2:
 * - Het dashboard vult bij elke sync de lijst othersData met het filter
 *   mainwp_sync_others_data ($othersData, $website) en stuurt die als JSON
 *   mee (class-mainwp-sync.php).
 * - De site leest die lijst en geeft hem door aan het filter
 *   mainwp_site_sync_others_data ($information, $othersData)
 *   (class-mainwp-child-stats.php, stats_others_data()). Wat daar aan
 *   $information wordt toegevoegd, gaat terug naar het dashboard.
 * - Op het dashboard komt dat binnen bij de actie mainwp_site_synced
 *   ($website, $information), alleen als de sync gelukt is.
 *
 * Het filter op de site draait bij elke sync, ook zonder vraag. De
 * extensies van MainWP voegen hun gegevens toch alleen toe als het
 * dashboard er met een eigen sleutel in othersData om vraagt. Hier ook:
 * zonder mediamora_toolkit in othersData gaat er niets mee.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MM_TOOLKIT_MAINWP_SLEUTEL = 'mediamora_toolkit';

/**
 * Versie van de structuur hieronder. Ophogen als die verandert, zodat het
 * dashboard weet wat het binnenkrijgt.
 */
const MM_TOOLKIT_MAINWP_SCHEMA = 1;

// Pas op plugins_loaded, want MainWP Child kan na de toolkit laden.
add_action( 'plugins_loaded', 'mm_toolkit_mainwp_haak' );

function mm_toolkit_mainwp_haak() {
	if ( defined( 'MAINWP_CHILD_FILE' ) ) {
		add_filter( 'mainwp_site_sync_others_data', 'mm_toolkit_mainwp_sync', 10, 2 );
	}
}

/**
 * Allebei komen van een andere plugin via een filter, dus eerst nakijken.
 *
 * @param mixed $information Wat MainWP Child terugstuurt naar het dashboard.
 * @param mixed $gevraagd    othersData van het dashboard.
 * @return mixed
 */
function mm_toolkit_mainwp_sync( $information, $gevraagd = array() ) {
	if ( ! is_array( $information ) || ! is_array( $gevraagd ) || empty( $gevraagd[ MM_TOOLKIT_MAINWP_SLEUTEL ] ) ) {
		return $information;
	}

	$information[ MM_TOOLKIT_MAINWP_SLEUTEL ] = mm_toolkit_mainwp_status();

	return $information;
}

/**
 * De status van de toolkit voor het dashboard. Geen persoonsgegevens, geen
 * volledige adressen, geen onderwerpen en geen instellingen.
 *
 * anti_spam en testinzending komen in een latere versie; tot dan null.
 *
 * @return array
 */
function mm_toolkit_mainwp_status() {
	$monitor = mm_toolkit_status()['formuliermonitor']['laden'] && function_exists( 'mm_monitor_sync_status' );

	return array(
		'schema'           => MM_TOOLKIT_MAINWP_SCHEMA,
		'toolkit_versie'   => mm_toolkit_huidige_versie(),
		'onderhoudsklant'  => mm_toolkit_onderhoudsklant(),
		'formuliermonitor' => $monitor
			? array( 'aan' => true ) + mm_monitor_sync_status()
			: array(
				'aan'                    => false,
				'storing'                => null,
				'laatste_fout'           => null,
				'laatste_geslaagde_mail' => null,
				'log'                    => array(),
			),
		'anti_spam'        => null,
		'testinzending'    => null,
	);
}
