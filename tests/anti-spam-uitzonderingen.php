<?php
/**
 * Mediamora Toolkit, testgevallen voor de uitgezonderde velden van de
 * anti-spam: het tekstveld lezen en terugzetten, en welke velden van welk
 * formulier de checks overslaan.
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/anti-spam-uitzonderingen.php
 * Geeft exitcode 1 als er een geval misgaat.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/mm-antispam-uitz-' . getmypid() );

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

$GLOBALS['opties'] = array(
	'mediamora_antispam_log_dir'  => LOGMAP,
	'mediamora_antispam_settings' => array(
		'debug'         => true,
		'exempt_fields' => array(
			'94c5fa7' => array( 'field_achternaam', 'field_klacht' ),
			'alles01' => array( '*' ),
		),
	),
);

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

// --- Het tekstveld lezen ---

$fouten = array();
$map    = mediamora_antispam_parse_exempt_fields( "  94c5fa7 :field_a,field_b ,  field_c\n\n\r\n04f3906: *\n", $fouten );
$test( 'spaties en lege regels tellen niet', array( '94c5fa7' => array( 'field_a', 'field_b', 'field_c' ), '04f3906' => array( '*' ) ) === $map && empty( $fouten ) );

$fouten = array();
$map    = mediamora_antispam_parse_exempt_fields( "abc: field_a\nabc: field_b, field_a", $fouten );
$test( 'zelfde formulier op twee regels telt samen, zonder dubbele', array( 'abc' => array( 'field_a', 'field_b' ) ) === $map && empty( $fouten ) );

$fouten = array();
$map    = mediamora_antispam_parse_exempt_fields( "abc: field_a, *\ndef: *\ndef: field_x", $fouten );
$test( '* samen met velden wordt alle velden', array( 'abc' => array( '*' ), 'def' => array( '*' ) ) === $map && empty( $fouten ) );

$fouten = array();
$map    = mediamora_antispam_parse_exempt_fields( "goed1: field_a\nzonder dubbele punt\n: field_a\nabc:\nab c: field_a\nabc: field a\nabc: <script>\ngoed2: field_b", $fouten );
$test( 'ongeldige regels worden niet opgeslagen', array( 'goed1' => array( 'field_a' ), 'goed2' => array( 'field_b' ) ) === $map );
$test( 'elke ongeldige regel geeft een melding', 6 === count( $fouten ) );
$test( 'melding noemt het regelnummer en de regel', isset( $fouten[0] ) && false !== strpos( $fouten[0], 'regel 2 ("zonder dubbele punt")' ) );

$fouten = array();
$test( 'leeg tekstveld is een lege lijst', array() === mediamora_antispam_parse_exempt_fields( "  \n ", $fouten ) && empty( $fouten ) );

// --- Terugzetten in het tekstveld ---

$tekst = mediamora_antispam_format_exempt_fields( array( '94c5fa7' => array( 'field_a', 'field_b' ), '04f3906' => array( '*' ) ) );
$test( 'terugzetten geeft één regel per formulier', "94c5fa7: field_a, field_b\n04f3906: *" === $tekst );
$fouten = array();
$test( 'terugzetten en weer lezen geeft hetzelfde', array( '94c5fa7' => array( 'field_a', 'field_b' ), '04f3906' => array( '*' ) ) === mediamora_antispam_parse_exempt_fields( $tekst, $fouten ) );
$test( 'lege lijst geeft een leeg tekstveld', '' === mediamora_antispam_format_exempt_fields( array() ) );

// --- Welk veld valt erbuiten ---

$map = array( '94c5fa7' => array( 'field_a' ), 'alles01' => array( '*' ) );
$test( 'uitgezonderd veld', mediamora_antispam_is_exempt_field( $map, '94c5fa7', 'field_a' ) );
$test( 'ander veld van hetzelfde formulier niet', ! mediamora_antispam_is_exempt_field( $map, '94c5fa7', 'field_b' ) );
$test( 'zelfde veld-ID in een ander formulier niet', ! mediamora_antispam_is_exempt_field( $map, '04f3906', 'field_a' ) );
$test( '* zondert elk veld uit', mediamora_antispam_is_exempt_field( $map, 'alles01', 'wat_dan_ook' ) );
$test( 'leeg formulier-ID nooit', ! mediamora_antispam_is_exempt_field( array( '' => array( '*' ) ), '', 'field_a' ) );
$test( 'geen lijst opgeslagen', ! mediamora_antispam_is_exempt_field( 'kapot', '94c5fa7', 'field_a' ) );
$test( 'formulier zonder lijst', ! mediamora_antispam_is_exempt_field( array( '94c5fa7' => 'field_a' ), '94c5fa7', 'field_a' ) );

// --- De validatie zelf ---

class Nep_Record {
	public $form_id;
	public $fields;
	public function __construct( $form_id, $fields ) {
		$this->form_id = $form_id;
		$this->fields  = $fields;
	}
	public function get_form_settings( $sleutel ) {
		return 'id' === $sleutel ? $this->form_id : null;
	}
	public function get( $sleutel ) {
		return 'fields' === $sleutel ? $this->fields : null;
	}
}

class Nep_Handler {
	public $fouten = array();
	public function add_error( $veld, $tekst ) {
		$this->fouten[] = $veld;
	}
}

$onzin  = 'xKqZpWvBnRtLmXcVb';
$velden = array(
	'field_voornaam'   => array( 'type' => 'text', 'value' => $onzin ),
	'field_achternaam' => array( 'type' => 'text', 'value' => $onzin . 'achternaam' ),
	'field_klacht'     => array( 'type' => 'textarea', 'value' => 'Kijk op https://bit.ly/3abc ' . $onzin . 'klacht' ),
);

$logbestand = mediamora_antispam_log_file();
$debug      = mediamora_antispam_debug_file();

$handler = new Nep_Handler();
mediamora_antispam_validate_form( new Nep_Record( '94c5fa7', $velden ), $handler );
$log = (string) @file_get_contents( $logbestand );
$dbg = (string) @file_get_contents( $debug );
$test( 'uitgezonderde velden krijgen geen fout', array( 'field_voornaam' ) === $handler->fouten );
$test( 'voornaam wordt nog gecontroleerd', false !== strpos( $log, 'field_voornaam' ) );
$test( 'uitgezonderde velden staan niet in log.txt', false === strpos( $log, 'achternaam' ) && false === strpos( $log, 'klacht' ) );
$test( 'waarde van uitgezonderd veld staat niet in debug.txt', false === strpos( $dbg, $onzin . 'achternaam' ) && false === strpos( $dbg, 'bit.ly' ) );
$test( 'debug.txt meldt het overslaan', 2 === substr_count( $dbg, 'overgeslagen (uitgezonderd veld)' ) );

$handler = new Nep_Handler();
mediamora_antispam_validate_form( new Nep_Record( '04f3906', $velden ), $handler );
$test( 'ander formulier: alle velden gecontroleerd', array( 'field_voornaam', 'field_achternaam', 'field_klacht' ) === $handler->fouten );

$handler = new Nep_Handler();
mediamora_antispam_validate_form( new Nep_Record( 'alles01', $velden ), $handler );
$test( 'formulier met *: geen enkel veld gecontroleerd', array() === $handler->fouten );

// Dubbele inhoud: een uitgezonderd veld telt niet mee als tweede kopie.
$lang    = str_repeat( 'Dit is een gewone zin over mijn klacht. ', 3 );
$handler = new Nep_Handler();
mediamora_antispam_validate_form(
	new Nep_Record(
		'94c5fa7',
		array(
			'field_bericht' => array( 'type' => 'textarea', 'value' => $lang ),
			'field_klacht'  => array( 'type' => 'textarea', 'value' => $lang ),
		)
	),
	$handler
);
$test( 'uitgezonderd veld telt niet mee voor dubbele inhoud', array() === $handler->fouten );

printf( "%d fout(en)\n", $fout );
exit( $fout ? 1 : 0 );
