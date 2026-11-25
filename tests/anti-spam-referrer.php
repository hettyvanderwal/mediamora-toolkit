<?php
/**
 * Mediamora Toolkit, testgevallen voor de referrercheck van 1.32.0 in de
 * anti-spam: alleen voor de ajax-actie elementor_pro_forms_send_form, alleen
 * leeg telt, de melding en de reden in de log, één logregel en geen
 * inhoudelijke checks meer, de schakelaar en de standaardwaarde bij een
 * upgrade.
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/anti-spam-referrer.php
 * Geeft exitcode 1 als er een geval misgaat.
 *
 * De instellingen van de module worden per proces één keer gelezen. Het deel
 * met de schakelaar uit draait daarom in een eigen proces.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$deel = $argv[1] ?? 'aan';

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/mm-antispam-referrer-' . getmypid() );

const LOGMAP = 'mediamora-antispam-testtest1234';

@mkdir( WP_CONTENT_DIR . '/uploads/' . LOGMAP, 0777, true );

register_shutdown_function(
	function () {
		array_map( 'unlink', glob( WP_CONTENT_DIR . '/uploads/' . LOGMAP . '/{*,.htaccess}', GLOB_BRACE ) ?: array() );
		@rmdir( WP_CONTENT_DIR . '/uploads/' . LOGMAP );
		@rmdir( WP_CONTENT_DIR . '/uploads' );
		@rmdir( WP_CONTENT_DIR );
	}
);

// Het modulebestand roept bij het laden alleen add_action en add_filter aan.
function add_action() {}
function add_filter() {}

$GLOBALS['opties'] = array( 'mediamora_antispam_log_dir' => LOGMAP );

if ( 'uit' === $deel ) {
	$GLOBALS['opties']['mediamora_antispam_settings'] = array( 'check_referrer' => false );
}

if ( 'upgrade' === $deel ) {
	// Opgeslagen instellingen van een site op 1.31.0: zonder check_referrer,
	// met een paar schakelaars die de beheerder heeft omgezet.
	$GLOBALS['opties']['mediamora_antispam_settings'] = array(
		'alert_email'    => 'iemand@voorbeeld.nl',
		'check_postcode' => false,
		'debug'          => false,
		'max_links_text' => 3,
	);
}

$GLOBALS['ajax'] = true;

function get_option( $naam, $standaard = false ) {
	return array_key_exists( $naam, $GLOBALS['opties'] ) ? $GLOBALS['opties'][ $naam ] : $standaard;
}
function update_option( $naam, $waarde ) {
	$GLOBALS['opties'][ $naam ] = $waarde;
	return true;
}
function wp_parse_args( $args, $standaard ) {
	return array_merge( $standaard, (array) $args );
}
function wp_next_scheduled() {
	return true; // geen mails inplannen
}
function wp_mkdir_p( $map ) {
	return @mkdir( $map, 0777, true );
}
function home_url() {
	return 'https://voorbeeld.nl';
}
function wp_parse_url( $url, $deel ) {
	return parse_url( $url, $deel );
}
function wp_doing_ajax() {
	return $GLOBALS['ajax'];
}
function wp_unslash( $waarde ) {
	return is_string( $waarde ) ? stripslashes( $waarde ) : $waarde;
}
function sanitize_email( $adres ) {
	return trim( $adres );
}
function is_email( $adres ) {
	return false !== filter_var( $adres, FILTER_VALIDATE_EMAIL );
}
function __( $tekst ) {
	return $tekst;
}
// Zoals WordPress met de tijdzone Europe/Amsterdam.
function wp_date( $formaat, $tijd = null ) {
	$datum = new DateTime( '@' . ( null === $tijd ? time() : $tijd ) );
	$datum->setTimezone( new DateTimeZone( 'Europe/Amsterdam' ) );
	return $datum->format( $formaat );
}

require dirname( __DIR__ ) . '/modules/anti-spam.php';

$fout = 0;
$test = function ( $naam, $goed ) use ( &$fout ) {
	echo ( $goed ? 'goed ' : 'FOUT ' ), $naam, "\n";
	$fout += $goed ? 0 : 1;
};

class Nep_Record {
	public $velden;
	public function __construct( $velden ) {
		$this->velden = $velden;
	}
	public function get_form_settings( $sleutel ) {
		if ( 'id' === $sleutel ) {
			return 'abc1234';
		}
		if ( 'form_name' === $sleutel ) {
			return 'Contact';
		}
		if ( 'form_fields' === $sleutel ) {
			$lijst = array();
			foreach ( $this->velden as $id => $veld ) {
				$lijst[] = array( 'custom_id' => $id, 'placeholder' => '' );
			}
			return $lijst;
		}
		return null;
	}
	public function get( $sleutel ) {
		return 'fields' === $sleutel ? $this->velden : null;
	}
}

class Nep_Handler {
	public $fouten = array();
	public function add_error( $veld, $tekst ) {
		$this->fouten[ $veld ] = $tekst;
	}
}

/**
 * Een inzending zoals admin-ajax.php hem binnenkrijgt. $post zijn de
 * POST-velden naast action; null voor referrer betekent: niet meegestuurd.
 */
function insturen( $post, $velden = null, $actie = 'elementor_pro_forms_send_form' ) {

	$_POST = array_merge(
		array(
			'post_id'       => '12',
			'form_id'       => 'abc1234',
			'referer_title' => 'Contact - Voorbeeld',
			'queried_id'    => '12',
		),
		$post
	);
	if ( null !== $actie ) {
		$_POST['action'] = $actie;
	}
	foreach ( $_POST as $sleutel => $waarde ) {
		if ( null === $waarde ) {
			unset( $_POST[ $sleutel ] );
		}
	}
	$_REQUEST = $_POST;

	if ( null === $velden ) {
		$velden = array(
			'name'    => array( 'type' => 'text', 'title' => 'Naam', 'value' => 'Jan de Vries' ),
			'message' => array( 'type' => 'textarea', 'title' => 'Bericht', 'value' => 'Hallo, ik wil graag een afspraak maken.' ),
		);
	}

	$GLOBALS['handler'] = new Nep_Handler();
	mediamora_antispam_validate_form( new Nep_Record( $velden ), $GLOBALS['handler'] );

	return $GLOBALS['handler']->fouten;
}

function logregels() {
	$bestand = mediamora_antispam_log_file();
	return file_exists( $bestand ) ? file( $bestand, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) : array();
}

$pagina = 'https://voorbeeld.nl/contact/';

if ( 'uit' === $deel ) {

	$test( 'schakelaar uit: zonder referrer mag', array() === insturen( array( 'referrer' => null ) ) );
	$test( 'schakelaar uit: lege referrer mag', array() === insturen( array( 'referrer' => '' ) ) );
	$test( 'schakelaar uit: andere checks werken nog', isset( insturen( array( 'referrer' => null ), array( 'name' => array( 'type' => 'text', 'title' => 'Naam', 'value' => 'mostbet_zlSl' ) ) )['name'] ) );

	printf( "%d fout(en)\n", $fout );
	exit( $fout ? 1 : 0 );
}

if ( 'upgrade' === $deel ) {

	$s = mediamora_antispam_settings();
	$test( 'upgrade: check_referrer staat aan', true === $s['check_referrer'] );
	$test( 'upgrade: check_postcode blijft uit', false === $s['check_postcode'] );
	$test( 'upgrade: meldingsadres blijft staan', 'iemand@voorbeeld.nl' === $s['alert_email'] );
	$test( 'upgrade: max. links blijft 3', 3 === $s['max_links_text'] );
	$test( 'upgrade: zonder referrer geweigerd', isset( insturen( array( 'referrer' => null ) )['referrer'] ) );

	printf( "%d fout(en)\n", $fout );
	exit( $fout ? 1 : 0 );
}

// --- Standaard en opslaan ---

$test( 'standaard aan', true === mediamora_antispam_default_settings()['check_referrer'] );

// --- Wel een referrer: gewoon door ---

$test( 'referrer van de pagina mag', array() === insturen( array( 'referrer' => $pagina ) ) );
$test( 'referrer van staging mag', array() === insturen( array( 'referrer' => 'https://staging.voorbeeld.nl/contact/' ) ) );
$test( 'referrer van een ander domein (WPML) mag', array() === insturen( array( 'referrer' => 'https://example.de/kontakt/' ) ) );
$test( 'referrer met querystring en anker mag', array() === insturen( array( 'referrer' => $pagina . '?utm_source=x#formulier' ) ) );
$test( 'met referrer werken de andere checks nog', isset( insturen( array( 'referrer' => $pagina ), array( 'name' => array( 'type' => 'text', 'title' => 'Naam', 'value' => 'mostbet_zlSl' ) ) )['name'] ) );

// --- Geen of een lege referrer: geweigerd ---

$voor   = count( logregels() );
$fouten = insturen( array( 'referrer' => null ) );
$regels = logregels();
$test( 'zonder referrer geweigerd', isset( $fouten['referrer'] ) );
$test( 'algemene melding', isset( $fouten['referrer'] ) && 'Ongeldige invoer gedetecteerd.' === $fouten['referrer'] );
$test( 'precies één logregel', count( $regels ) === $voor + 1 );
$test(
	'reden en formulier in de log',
	false !== strpos( (string) end( $regels ), '| veld: referrer (n.v.t.) | reden: verstuurd zonder JavaScript (geen referrer) | waarde: formulier: Contact (abc1234), referer_title: Contact - Voorbeeld' )
);
$test( 'logregel heeft een tijdstip dat het rapport kan lezen', false !== mediamora_antispam_parse_line_timestamp( (string) end( $regels ) ) );

$test( 'lege referrer geweigerd', isset( insturen( array( 'referrer' => '' ) )['referrer'] ) );
$test( 'referrer met alleen witruimte geweigerd', isset( insturen( array( 'referrer' => "  \n" ) )['referrer'] ) );
$test( 'referrer als lijst geweigerd', isset( insturen( array( 'referrer' => array( $pagina ) ) )['referrer'] ) );

// Vóór de inhoudelijke checks: een spamveld geeft geen eigen fout of logregel meer.
$voor   = count( logregels() );
$fouten = insturen(
	array( 'referrer' => null ),
	array(
		'name'     => array( 'type' => 'text', 'title' => 'Naam', 'value' => 'RobertPhirl_xx' ),
		'postcode' => array( 'type' => 'text', 'title' => 'Postcode', 'value' => '122551' ),
	)
);
$test( 'zonder referrer: alleen de referrerfout', array( 'referrer' ) === array_keys( $fouten ) );
$test( 'zonder referrer: geen logregels van andere checks', count( logregels() ) === $voor + 1 );

// Zonder referer_title in de log: (leeg).
insturen( array( 'referrer' => null, 'referer_title' => null ) );
$regels = logregels();
$test( 'zonder referer_title staat (leeg) in de log', false !== strpos( (string) end( $regels ), 'referer_title: (leeg)' ) );

// --- Alleen de klassieke ajax-actie ---

$test( 'atomic formulier (elementor_pro_atomic_forms_send_form): niet gecontroleerd', array() === insturen( array( 'referrer' => null ), null, 'elementor_pro_atomic_forms_send_form' ) );
$test( 'andere ajax-actie: niet gecontroleerd', array() === insturen( array( 'referrer' => null ), null, 'iets_anders' ) );
$test( 'geen actie: niet gecontroleerd', array() === insturen( array( 'referrer' => null ), null, null ) );
$GLOBALS['ajax'] = false;
$test( 'geen ajax-aanvraag: niet gecontroleerd', array() === insturen( array( 'referrer' => null ) ) );
$GLOBALS['ajax'] = true;

// --- De schakelaar opslaan ---

$opgeslagen = mediamora_antispam_sanitize_and_save_settings( array( 'alert_email' => 'iemand@voorbeeld.nl' ) );
$test( 'vinkje uit opgeslagen', false === $opgeslagen['check_referrer'] && false === $GLOBALS['opties']['mediamora_antispam_settings']['check_referrer'] );
$opgeslagen = mediamora_antispam_sanitize_and_save_settings( array( 'alert_email' => 'iemand@voorbeeld.nl', 'check_referrer' => '1' ) );
$test( 'vinkje aan opgeslagen', true === $opgeslagen['check_referrer'] && true === $GLOBALS['opties']['mediamora_antispam_settings']['check_referrer'] );

printf( "%d fout(en)\n", $fout );

$uit     = 0;
$upgrade = 0;
passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' uit', $uit );
passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' upgrade', $upgrade );

exit( $fout || $uit || $upgrade ? 1 : 0 );
