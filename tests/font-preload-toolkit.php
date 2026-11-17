<?php
/**
 * Mediamora Toolkit, testgevallen voor de stand van Lettertype voorladen:
 * standaard uit, ook op een site die de instelling nooit heeft opgeslagen,
 * en aan met een opgeslagen keuze of de constante.
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/font-preload-toolkit.php
 * Geeft exitcode 1 als er een geval misgaat.
 *
 * Het hoofdbestand laadt de modules bij het inladen, dus elk scenario draait
 * in een eigen PHP-proces. Alle andere modules staan uit.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

// Per scenario: opgeslagen keuze (null is nooit opgeslagen, zoals na de
// update vanaf 1.22.0), constante (null is niet gezet), en de verwachting:
// gewild en laadt de module.
$scenarios = array(
	'nooit_opgeslagen' => array( null, null, false ),
	'opgeslagen_uit'   => array( false, null, false ),
	'opgeslagen_aan'   => array( true, null, true ),
	'constante_aan'    => array( null, true, true ),
	'constante_uit'    => array( true, false, false ),
	'nieuwe_site'      => array( 'nieuw', null, false ),
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
list( $keuze, $constante, $laadt ) = $scenarios[ $scenario ];

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
if ( null !== $constante ) {
	define( 'MM_TOOLKIT_MODULE_FONT_PRELOAD', $constante );
}

$GLOBALS['hooks'] = array();

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
	'afbeeldingstitels'   => false,
);
if ( is_bool( $keuze ) ) {
	$keuzes['font_preload'] = $keuze;
}
$GLOBALS['opties'] = array( 'mm_toolkit_onderhoudsklant' => 0 );
if ( 'nieuw' !== $keuze ) {
	$GLOBALS['opties']['mm_toolkit_modules']                 = $keuzes;
	$GLOBALS['opties']['mm_toolkit_afbeeldingstitels_stand'] = 0;
}

function add_action( $hook, $functie ) {
	$GLOBALS['hooks'][ $hook ][] = $functie;
}
function add_filter( $hook, $functie ) {
	$GLOBALS['hooks'][ $hook ][] = $functie;
}
function do_action() {}
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
function add_option( $naam, $waarde ) {
	if ( ! array_key_exists( $naam, $GLOBALS['opties'] ) ) {
		$GLOBALS['opties'][ $naam ] = $waarde;
	}
}
function delete_option( $naam ) {
	unset( $GLOBALS['opties'][ $naam ] );
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
$module = mm_toolkit_modules()['font_preload'];
$status = mm_toolkit_status()['font_preload'];

klopt( $module['tabblad'], 'snelheid', 'tabblad' );
klopt( $module['standaard'], false, 'standaard uit' );
klopt( $status['gewild'], $laadt, 'gewild' );
klopt( $status['vast'], null !== $constante, 'vast' );
klopt( $status['los'], '', 'geen losse versie' );
klopt( $status['laden'], $laadt, 'laadt' );
klopt( function_exists( 'mm_font_preload_head' ), $laadt, 'modulefuncties alleen als de module laadt' );
klopt( isset( $GLOBALS['hooks']['wp_head'] ) && in_array( 'mm_font_preload_head', $GLOBALS['hooks']['wp_head'], true ), $laadt, 'wp_head alleen als de module laadt' );

// Een nieuwe site krijgt bij het activeren de standaard: uit.
if ( 'nieuw' === $keuze ) {
	mm_toolkit_activeren();
	klopt( get_option( 'mm_toolkit_modules' )['font_preload'], false, 'activeren legt uit vast' );
}

printf( "%s: %d van %d gevallen goed\n", $scenario, $goed, $totaal );
exit( $goed === $totaal ? 0 : 1 );
