<?php
/**
 * Mediamora Toolkit, testgevallen voor de koppeling van Tijdelijke accounts
 * met het hoofdbestand: laden, de overgang van de mu-plugin, de melding, het
 * zelfherstel en het mailvinkje.
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/tijdelijke-accounts-toolkit.php
 * Geeft exitcode 1 als er een geval misgaat.
 *
 * Het hoofdbestand laadt de modules bij het inladen, dus elk scenario draait
 * in een eigen PHP-proces. Alle andere modules staan uit, zodat alleen deze
 * module meedoet.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

if ( ! isset( $argv[1] ) ) {
	$fout = 0;
	foreach ( array( 'aan', 'met_mu', 'uit', 'uit_met_mu' ) as $scenario ) {
		$regels = array();
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . $scenario . ' 2>&1', $regels, $code );
		echo implode( "\n", $regels ), "\n";
		$fout += $code ? 1 : 0;
	}
	exit( $fout ? 1 : 0 );
}

$scenario = $argv[1];

$map = sys_get_temp_dir() . '/mm-toolkit-test-' . getmypid();
mkdir( $map );
if ( in_array( $scenario, array( 'met_mu', 'uit_met_mu' ), true ) ) {
	touch( $map . '/mediamora-tijdelijke-accounts.php' );
}
register_shutdown_function(
	function () use ( $map ) {
		@unlink( $map . '/mediamora-tijdelijke-accounts.php' );
		@rmdir( $map );
	}
);

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPMU_PLUGIN_DIR', $map );
define( 'WP_PLUGIN_DIR', $map );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['hooks']     = array();
$GLOBALS['mediamora'] = true;
$GLOBALS['cron']      = array( 'mm_tijdelijke_accounts_opruimen' => 12345 );

// Alle andere modules uit. Tijdelijke accounts staat aan of uit per scenario;
// bij 'aan' en 'met_mu' geldt de standaard, dus ontbreekt de sleutel.
$keuzes = array(
	'alt_teksten'       => false,
	'hero_preload'      => false,
	'preview_link'      => false,
	'anti_spam'         => false,
	'popup_tik'         => false,
	'jquery_wacht'      => false,
	'schema_basis'      => false,
	'afbeelding_ruimte' => false,
	'elementor_versie'  => false,
);
if ( in_array( $scenario, array( 'uit', 'uit_met_mu' ), true ) ) {
	$keuzes['tijdelijke_accounts'] = false;
}
$GLOBALS['opties'] = array(
	'mm_toolkit_modules'         => $keuzes,
	'mm_toolkit_onderhoudsklant' => 0,
);

class Doorsturen extends Exception {}

function add_action( $hook, $functie ) {
	$GLOBALS['hooks'][ $hook ][] = $functie;
}
function add_filter( $hook, $functie ) {
	$GLOBALS['hooks'][ $hook ][] = $functie;
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
function is_user_logged_in() {
	return true;
}
function wp_get_current_user() {
	return (object) array( 'user_email' => $GLOBALS['mediamora'] ? 'hetty@mediamora.nl' : 'klant@voorbeeld.nl' );
}
function current_user_can() {
	return true;
}
function check_admin_referer() {
	return true;
}
function sanitize_key( $w ) {
	return strtolower( $w );
}
function wp_unslash( $w ) {
	return $w;
}
function esc_html( $w ) {
	return htmlspecialchars( (string) $w, ENT_QUOTES );
}
function wp_next_scheduled( $hook ) {
	return isset( $GLOBALS['cron'][ $hook ] ) ? $GLOBALS['cron'][ $hook ] : false;
}
function wp_unschedule_hook( $hook ) {
	unset( $GLOBALS['cron'][ $hook ] );
}
function wp_clear_scheduled_hook( $hook ) {
	unset( $GLOBALS['cron'][ $hook ] );
}
// Geen .htaccess in de testmap, dus de echte blokfuncties doen niets.
function get_home_path() {
	return WPMU_PLUGIN_DIR . '/';
}
function delete_option() {}
function admin_url( $pad = '' ) {
	return $pad;
}
function add_query_arg( $k, $v, $url ) {
	return $url;
}
function wp_safe_redirect() {
	throw new Doorsturen();
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

function melding() {
	ob_start();
	mm_toolkit_melding();
	return ob_get_clean();
}

function opslaan( $post ) {
	$_POST = array_merge( array( 'mm_tab' => 'mediamora' ), $post );
	try {
		mm_toolkit_opslaan();
	} catch ( Doorsturen $e ) {
		return;
	}
}

$goed    = 0;
$totaal  = 0;
$status  = mm_toolkit_status()['tijdelijke_accounts'];
$module  = mm_toolkit_modules()['tijdelijke_accounts'];
$bericht = 'kan weg';

klopt( $module['tabblad'], 'mediamora', 'tabblad' );
klopt( $module['onderhoud'], false, 'niet onder Onderhoudsklant' );

switch ( $scenario ) {

	case 'aan':
		klopt( $status['laden'], true, 'laadt zonder Onderhoudsklant en met de standaard' );
		klopt( function_exists( 'mm_tijdelijk_opruimen' ), true, 'module geladen' );
		klopt( isset( $GLOBALS['hooks']['determine_current_user'] ), true, 'hooks geregistreerd' );
		klopt( melding(), '', 'geen melding' );

		mm_toolkit_meta_opruimen_herstel();
		klopt( wp_next_scheduled( 'mm_tijdelijke_accounts_opruimen' ), 12345, 'event van de losse versie blijft staan' );

		opslaan( array( 'mm_tijdelijk_mail_getoond' => '1' ) );
		klopt( $GLOBALS['opties']['mm_tijdelijke_accounts_mail'], 0, 'vinkje uit wordt 0' );
		opslaan( array( 'mm_tijdelijk_mail_getoond' => '1', 'mm_tijdelijk_mail' => '1' ) );
		klopt( $GLOBALS['opties']['mm_tijdelijke_accounts_mail'], 1, 'vinkje aan wordt 1' );

		mm_toolkit_deactiveren();
		klopt( wp_next_scheduled( 'mm_tijdelijke_accounts_opruimen' ), false, 'deactiveren haalt het event weg' );
		break;

	case 'met_mu':
		klopt( $status['laden'], false, 'laadt niet naast de mu-plugin' );
		klopt( $status['los'], 'mu-plugin mediamora-tijdelijke-accounts.php', 'losse versie gezien' );
		klopt( function_exists( 'mm_tijdelijk_opruimen' ), false, 'module niet geladen' );
		klopt( isset( $GLOBALS['hooks']['determine_current_user'] ), false, 'geen hooks' );
		klopt( isset( $GLOBALS['hooks']['mm_tijdelijke_accounts_opruimen'] ), false, 'geen cronhook' );

		klopt( false !== strpos( melding(), $bericht ), true, 'melding voor Mediamora' );
		klopt( false !== strpos( melding(), 'wp-content/mu-plugins/mediamora-tijdelijke-accounts.php' ), true, 'melding noemt het bestand' );
		$GLOBALS['mediamora'] = false;
		klopt( melding(), '', 'geen melding voor de klant' );
		$GLOBALS['mediamora'] = true;

		mm_toolkit_meta_opruimen_herstel();
		klopt( wp_next_scheduled( 'mm_tijdelijke_accounts_opruimen' ), 12345, 'zelfherstel laat het event van de mu-plugin staan' );

		mm_toolkit_deactiveren();
		klopt( wp_next_scheduled( 'mm_tijdelijke_accounts_opruimen' ), 12345, 'deactiveren laat het event van de mu-plugin staan' );

		$GLOBALS['opties']['mm_tijdelijke_accounts_mail'] = 1;
		opslaan( array() );
		klopt( $GLOBALS['opties']['mm_tijdelijke_accounts_mail'], 1, 'opslaan zonder vinkje op het scherm laat de mail staan' );
		break;

	case 'uit':
		klopt( $status['laden'], false, 'uit' );
		mm_toolkit_meta_opruimen_herstel();
		klopt( wp_next_scheduled( 'mm_tijdelijke_accounts_opruimen' ), false, 'zelfherstel haalt het event weg' );
		klopt( melding(), '', 'geen melding' );
		break;

	case 'uit_met_mu':
		klopt( false !== strpos( melding(), $bericht ), false, 'uit: bestand kan niet zomaar weg, dus geen melding' );
		mm_toolkit_meta_opruimen_herstel();
		klopt( wp_next_scheduled( 'mm_tijdelijke_accounts_opruimen' ), 12345, 'event van de mu-plugin blijft staan' );
		break;
}

printf( "%s: %d van %d gevallen goed\n", $scenario, $goed, $totaal );
exit( $goed === $totaal ? 0 : 1 );
