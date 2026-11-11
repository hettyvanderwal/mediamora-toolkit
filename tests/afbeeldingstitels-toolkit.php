<?php
/**
 * Mediamora Toolkit, testgevallen voor het legen van de caches bij aan- en
 * uitzetten van Afbeeldingstitels verbergen: de update, de schakelaar, de
 * constante en deactiveren.
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/afbeeldingstitels-toolkit.php
 * Geeft exitcode 1 als er een geval misgaat.
 *
 * Het hoofdbestand laadt de modules bij het inladen, dus elk scenario draait
 * in een eigen PHP-proces. Alle andere modules staan uit.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

// Per scenario: opgeslagen keuze (null is de standaard), opgeslagen stand
// (null is nog geen stand), constante, LiteSpeed actief, en de verwachting:
// laadt de module, wordt er geleegd, en de stand daarna.
$scenarios = array(
	'update_aan'       => array( null, null, null, true, true, true, 1 ),
	'nieuw_uit'        => array( false, null, null, true, false, false, 0 ),
	'blijft_aan'       => array( null, 1, null, true, true, false, 1 ),
	'blijft_uit'       => array( false, 0, null, true, false, false, 0 ),
	'uitgezet'         => array( false, 1, null, true, false, true, 0 ),
	'aangezet'         => array( true, 0, null, true, true, true, 1 ),
	'constante_uit'    => array( true, 1, false, true, false, true, 0 ),
	'zonder_litespeed' => array( false, 1, null, false, false, true, 0 ),
	'deactiveren'      => array( null, 1, null, true, true, false, 1 ),
);

if ( ! isset( $argv[1] ) ) {
	$fout = 0;
	foreach ( array_keys( $scenarios ) as $scenario ) {
		$regels = array();
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . $scenario . ' 2>&1', $regels, $code );
		echo implode( "\n", $regels ), "\n";
		$fout += $code ? 1 : 0;
	}
	exit( $fout ? 1 : 0 );
}

$scenario = $argv[1];
list( $keuze, $stand, $constante, $litespeed, $laadt, $leeg, $stand_na ) = $scenarios[ $scenario ];

$map = sys_get_temp_dir() . '/mm-toolkit-test-' . getmypid();
mkdir( $map );
register_shutdown_function(
	function () use ( $map ) {
		@rmdir( $map );
	}
);

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPMU_PLUGIN_DIR', $map );
define( 'WP_PLUGIN_DIR', $map );
define( 'HOUR_IN_SECONDS', 3600 );
if ( null !== $constante ) {
	define( 'MM_TOOLKIT_MODULE_AFBEELDINGSTITELS', $constante );
}
if ( $litespeed ) {
	define( 'LSCWP_V', '7.9.3' );
}

$GLOBALS['hooks']   = array();
$GLOBALS['geleegd'] = array();
$GLOBALS['acties']  = array();

$keuzes = array(
	'alt_teksten'         => false,
	'hero_preload'        => false,
	'preview_link'        => false,
	'anti_spam'           => false,
	'popup_tik'           => false,
	'jquery_wacht'        => false,
	'schema_basis'        => false,
	'afbeelding_ruimte'   => false,
	'elementor_versie'    => false,
	'tijdelijke_accounts' => false,
);
if ( null !== $keuze ) {
	$keuzes['afbeeldingstitels'] = $keuze;
}
$GLOBALS['opties'] = array(
	'mm_toolkit_modules'         => $keuzes,
	'mm_toolkit_onderhoudsklant' => 0,
);
if ( null !== $stand ) {
	$GLOBALS['opties']['mm_toolkit_afbeeldingstitels_stand'] = $stand;
}

function add_action( $hook, $functie ) {
	$GLOBALS['hooks'][ $hook ][] = $functie;
}
function add_filter( $hook, $functie ) {
	$GLOBALS['hooks'][ $hook ][] = $functie;
}
function do_action( $hook ) {
	$GLOBALS['acties'][] = $hook;
}
function add_shortcode() {}
function register_activation_hook() {}
function register_deactivation_hook() {}
function plugin_basename( $bestand ) {
	return basename( $bestand );
}
function apply_filters( $hook, $waarde ) {
	return $waarde;
}
function did_action() {
	return 0;
}
function is_multisite() {
	return false;
}
function delete_transient() {}
function get_option( $naam, $standaard = false ) {
	return array_key_exists( $naam, $GLOBALS['opties'] ) ? $GLOBALS['opties'][ $naam ] : $standaard;
}
function update_option( $naam, $waarde ) {
	$GLOBALS['opties'][ $naam ] = $waarde;
}
function delete_option( $naam ) {
	unset( $GLOBALS['opties'][ $naam ] );
}
function delete_post_meta_by_key( $sleutel ) {
	$GLOBALS['geleegd'][] = $sleutel;
}
function wp_unschedule_hook() {}
function wp_clear_scheduled_hook() {}
// Geen .htaccess in de testmap, dus de echte blokfuncties doen niets.
function get_home_path() {
	return WPMU_PLUGIN_DIR . '/';
}

require dirname( __DIR__ ) . '/mediamora-toolkit.php';

function klopt( $uitkomst, $verwacht, $wat ) {
	global $goed, $totaal, $scenario;
	$totaal++;
	if ( $uitkomst === $verwacht ) {
		$goed++;
		return;
	}
	printf( "FOUT  [%s] %s: %s, verwacht %s\n", $scenario, $wat, var_export( $uitkomst, true ), var_export( $verwacht, true ) );
}

$goed   = 0;
$totaal = 0;
$module = mm_toolkit_modules()['afbeeldingstitels'];

klopt( $module['tabblad'], 'vindbaarheid', 'tabblad' );
klopt( $module['standaard'], true, 'standaard aan' );
klopt( in_array( 'mm_toolkit_afbeeldingstitels_herstel', $GLOBALS['hooks']['init'], true ), true, 'zelfherstel op init' );
klopt( mm_toolkit_status()['afbeeldingstitels']['laden'], $laadt, 'laadt' );
klopt( function_exists( 'mm_verberg_afbeeldingstitel' ), $laadt, 'filterfunctie bestaat alleen als de module laadt' );
klopt( isset( $GLOBALS['hooks']['elementor/image_size/get_attachment_image_html'] ), $laadt, 'filter alleen als de module laadt' );

mm_toolkit_afbeeldingstitels_herstel();

klopt( $GLOBALS['geleegd'], $leeg ? array( '_elementor_element_cache' ) : array(), 'element cache geleegd' );
klopt( $GLOBALS['acties'], $leeg && $litespeed ? array( 'litespeed_purge_all' ) : array(), 'LiteSpeed-cache geleegd' );
klopt( get_option( 'mm_toolkit_afbeeldingstitels_stand', null ), $stand_na, 'stand daarna' );

// Een tweede verzoek met dezelfde stand leegt niets meer.
$GLOBALS['geleegd'] = array();
$GLOBALS['acties']  = array();
mm_toolkit_afbeeldingstitels_herstel();
klopt( $GLOBALS['geleegd'], array(), 'tweede keer niets geleegd' );

if ( 'deactiveren' === $scenario ) {
	mm_toolkit_deactiveren();
	klopt( $GLOBALS['geleegd'], array( '_elementor_element_cache' ), 'deactiveren leegt de element cache' );
	klopt( $GLOBALS['acties'], array( 'litespeed_purge_all' ), 'deactiveren leegt LiteSpeed' );
	klopt( get_option( 'mm_toolkit_afbeeldingstitels_stand', null ), null, 'deactiveren haalt de stand weg' );

	// Opnieuw activeren: de stand ontbreekt en de module laadt, dus weer legen.
	$GLOBALS['geleegd'] = array();
	mm_toolkit_afbeeldingstitels_herstel();
	klopt( $GLOBALS['geleegd'], array( '_elementor_element_cache' ), 'na opnieuw activeren weer geleegd' );
}

printf( "%s: %d van %d gevallen goed\n", $scenario, $goed, $totaal );
exit( $goed === $totaal ? 0 : 1 );
