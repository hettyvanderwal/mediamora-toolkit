<?php
/**
 * Mediamora Toolkit, opruimen bij het verwijderen van de plugin.
 *
 * WordPress laadt alleen dit bestand, niet de rest van de plugin. De lijst
 * met modules en het .htaccess-blok komen daarom uit includes/.
 *
 * De losse versies van de modules gebruiken dezelfde opties, bestanden en
 * meta. Staat de losse versie van een module nog op de site, als plugin of
 * mu-plugin en actief of niet, dan blijft alles van die module staan.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

define( 'MM_TOOLKIT_MAP', __DIR__ );

require_once MM_TOOLKIT_MAP . '/includes/modules.php';
require_once MM_TOOLKIT_MAP . '/includes/preview-htaccess.php';

/**
 * Staat de losse versie van deze module nog op de site? Anders dan
 * mm_toolkit_losse_versie() telt hier ook een plugin die niet actief is.
 *
 * @param array $module Een regel uit mm_toolkit_modules().
 * @return bool
 */
function mm_toolkit_uninstall_los_aanwezig( $module ) {

	if ( $module['losse_mu'] && defined( 'WPMU_PLUGIN_DIR' ) && file_exists( WPMU_PLUGIN_DIR . '/' . $module['losse_mu'] ) ) {
		return true;
	}

	if ( $module['losse_plugin'] && defined( 'WP_PLUGIN_DIR' ) && file_exists( WP_PLUGIN_DIR . '/' . $module['losse_plugin'] ) ) {
		return true;
	}

	if ( ! empty( $module['merkteken'] ) ) {
		list( $soort, $naam ) = $module['merkteken'];
		if ( ( 'class' === $soort && class_exists( $naam, false ) ) || ( 'function' === $soort && function_exists( $naam ) ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Verwijdert een logmap van de anti-spam met alles erin. Er komen daar
 * alleen losse bestanden in; staat er toch een submap, dan blijft de map
 * staan in plaats van dat we gaan graven.
 *
 * @param string $dir Absoluut pad zonder slash aan het eind.
 * @return bool True als de map daarna weg is.
 */
function mm_toolkit_uninstall_logmap_weg( $dir ) {

	if ( ! is_dir( $dir ) ) {
		return true;
	}

	$inhoud = @scandir( $dir );

	if ( false === $inhoud ) {
		return false;
	}

	foreach ( array_diff( $inhoud, array( '.', '..' ) ) as $bestand ) {
		$pad = $dir . '/' . $bestand;
		if ( is_dir( $pad ) && ! is_link( $pad ) ) {
			return false;
		}
		@unlink( $pad );
	}

	@rmdir( $dir );
	clearstatcache( true, $dir );

	return ! is_dir( $dir );
}

/**
 * Ruimt de opties, transients, cron-events en meta van één site op.
 *
 * @param bool[] $los Per modulesleutel: staat de losse versie er nog?
 */
function mm_toolkit_uninstall_site( $los ) {
	global $wpdb;

	delete_option( 'mm_toolkit_modules' );
	delete_transient( 'mm_toolkit_release' );
	delete_transient( 'mm_toolkit_preview_htaccess_fout' );

	if ( ! $los['alt_teksten'] ) {
		foreach ( array( '_mm_alt_bron', '_mm_alt_index', '_mm_alt_datum' ) as $sleutel ) {
			delete_metadata( 'post', 0, $sleutel, '', true );
		}
	}

	if ( ! $los['preview_link'] ) {
		delete_option( 'mm_preview_key' );
	}

	if ( ! $los['anti_spam'] ) {

		wp_clear_scheduled_hook( 'mediamora_antispam_report' );
		wp_clear_scheduled_hook( 'mediamora_antispam_nearmiss_alert' );

		// Eerst de map, dan pas de optie met de naam. Lukt het weghalen niet,
		// dan blijft de optie staan, zodat nog te vinden is waar de map met
		// inzendingen staat. Een naam die niet klopt kan niet van ons zijn,
		// dus daar wordt niets weggegooid.
		$naam = get_option( 'mediamora_antispam_log_dir', '' );
		$weg  = true;
		if ( is_string( $naam ) && preg_match( '/^mediamora-antispam-[a-z0-9]{8,32}$/', $naam ) ) {
			$weg = mm_toolkit_uninstall_logmap_weg( WP_CONTENT_DIR . '/uploads/' . $naam );
		}
		if ( $weg ) {
			delete_option( 'mediamora_antispam_log_dir' );
		}

		delete_option( 'mediamora_antispam_settings' );
		delete_option( 'mediamora_antispam_last_report' );
		delete_option( 'mediamora_antispam_last_nearmiss_alert' );
		delete_option( 'mediamora_antispam_last_debug_prune' );
	}

	if ( ! $los['formuliermonitor'] ) {
		delete_option( 'mm_monitor_teller' );
		delete_option( 'mm_monitor_log' );
	}

	if ( ! $los['ai_bots'] ) {
		wp_clear_scheduled_hook( 'mm_aibots_opschonen' );
		$namen = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'mm\\_aibots\\_%'"
		);
		foreach ( (array) $namen as $naam ) {
			delete_option( $naam );
		}
	}
}

$mm_toolkit_los = array();
foreach ( mm_toolkit_modules() as $mm_toolkit_sleutel => $mm_toolkit_module ) {
	$mm_toolkit_los[ $mm_toolkit_sleutel ] = mm_toolkit_uninstall_los_aanwezig( $mm_toolkit_module );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $mm_toolkit_site ) {
		switch_to_blog( $mm_toolkit_site );
		mm_toolkit_uninstall_site( $mm_toolkit_los );
		restore_current_blog();
	}
} else {
	mm_toolkit_uninstall_site( $mm_toolkit_los );
}

// De vaste logmap van voor 1.1.0 is er maar één voor de hele installatie.
if ( ! $mm_toolkit_los['anti_spam'] ) {
	mm_toolkit_uninstall_logmap_weg( WP_CONTENT_DIR . '/uploads/mediamora-antispam' );
}

// Normaal al weg bij het deactiveren. Eén .htaccess voor de hele installatie.
mm_toolkit_preview_cache_bijwerken( false );
