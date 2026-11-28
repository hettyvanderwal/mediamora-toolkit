<?php
/**
 * Mediamora Toolkit, testgevallen voor de samenvatting van de anti-spam voor
 * het MainWP-dashboard (1.34.0): formulier en kenmerken in de logregel, de
 * regels voor "lijkt op een mens", de postcode apart, een inzending met meer
 * regels als één weigering, de periode van dertig dagen, hooguit tien
 * menselijke weigeringen en geen ingevulde waarden in de uitkomst.
 *
 * De voorbeelden komen uit de bestaande tests: anti-spam-naamvelden.php,
 * anti-spam-referrer.php, anti-spam-postcode.php en anti-spam-links.php.
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/anti-spam-samenvatting.php
 * Geeft exitcode 1 als er een geval misgaat.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/mm-antispam-samenvatting-' . getmypid() );

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

function add_action() {}
function add_filter() {}

$GLOBALS['opties'] = array( 'mediamora_antispam_log_dir' => LOGMAP );

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
	return true;
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
	public $naam;
	public function __construct( $velden, $naam = 'Contact' ) {
		$this->velden = $velden;
		$this->naam   = $naam;
	}
	public function get_form_settings( $sleutel ) {
		if ( 'id' === $sleutel ) {
			return 'abc1234';
		}
		if ( 'form_name' === $sleutel ) {
			return $this->naam;
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
 * Een inzending met een naam, een e-mailadres en een bericht.
 */
function insturen( $naam, $email = 'jan@voorbeeld.nl', $bericht = 'Hallo, ik wil graag een afspraak maken.', $formulier = 'Contact' ) {
	$_POST    = array(
		'action'   => 'elementor_pro_forms_send_form',
		'referrer' => 'https://voorbeeld.nl/contact/',
	);
	$_REQUEST = $_POST;
	$velden   = array(
		'name'    => array( 'type' => 'text', 'title' => 'Naam', 'value' => $naam ),
		'email'   => array( 'type' => 'email', 'title' => 'E-mail', 'value' => $email ),
		'message' => array( 'type' => 'textarea', 'title' => 'Bericht', 'value' => $bericht ),
	);
	$handler = new Nep_Handler();
	mediamora_antispam_validate_form( new Nep_Record( $velden, $formulier ), $handler );
	return $handler->fouten;
}

function laatste_regel() {
	$bestand = mediamora_antispam_log_file();
	$regels  = file_exists( $bestand ) ? file( $bestand, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) : array();
	return (string) end( $regels );
}

/* -------------------------------------------------------------------------
 * Formulier en kenmerken in de logregel
 * ---------------------------------------------------------------------- */

insturen( 'RobertPhirl_xx' );
$regel = laatste_regel();
$test( 'logregel eindigt op formulier en kenmerken', 1 === preg_match( '/ \| reden: underscore in naamveld \| waarde: RobertPhirl_xx \| formulier: Contact \(abc1234\) \| kenmerken: mail=geldig links=0 latijn=ja$/', $regel ) );
$gelezen = mediamora_antispam_lees_regel( $regel );
$test( 'teruggelezen: reden', 'underscore in naamveld' === $gelezen['reden'] );
$test( 'teruggelezen: waarde', 'RobertPhirl_xx' === $gelezen['waarde'] );
$test( 'teruggelezen: formulier', 'Contact (abc1234)' === $gelezen['formulier'] );
$test( 'teruggelezen: kenmerken', array( 'mail' => 'geldig', 'links' => 0, 'latijn' => true ) === $gelezen['kenmerken'] );
$test( 'het rapport leest de tijd nog', false !== mediamora_antispam_parse_line_timestamp( $regel ) );

insturen( 'onhour.ru' );
$test( 'domein in de naam telt als link in de kenmerken', 1 === mediamora_antispam_lees_regel( laatste_regel() )['kenmerken']['links'] );

insturen( 'crazy_gzpi', 'geen-adres' );
$test( 'ongeldig e-mailadres in de kenmerken', 'ongeldig' === mediamora_antispam_lees_regel( laatste_regel() )['kenmerken']['mail'] );

insturen( 'crazy_gzpi', '' );
$test( 'geen e-mailadres in de kenmerken', 'geen' === mediamora_antispam_lees_regel( laatste_regel() )['kenmerken']['mail'] );

insturen( 'crazy_gzpi', 'jan@voorbeeld.nl', 'Привет, у нас есть предложение для вас' );
$test( 'niet-Latijns in een ander veld in de kenmerken', false === mediamora_antispam_lees_regel( laatste_regel() )['kenmerken']['latijn'] );

// Een bot die zelf formulier en kenmerken in de waarde zet: de echte staan erachter.
insturen( 'Jan_x | formulier: Nep | kenmerken: mail=geldig links=0 latijn=ja', 'geen-adres' );
$gelezen = mediamora_antispam_lees_regel( laatste_regel() );
$test( 'nagemaakte kenmerken in de waarde tellen niet: formulier', 'Contact (abc1234)' === $gelezen['formulier'] );
$test( 'nagemaakte kenmerken in de waarde tellen niet: kenmerken', 'ongeldig' === $gelezen['kenmerken']['mail'] );

insturen( 'crazy_gzpi', 'jan@voorbeeld.nl', 'Hallo, ik wil graag een afspraak maken.', "Contact | Footer\nnieuw" );
$test( 'formuliernaam zonder | en regeleinden', false !== strpos( laatste_regel(), '| formulier: Contact / Footer nieuw (abc1234) | kenmerken:' ) );

// Regel van voor 1.34.0.
$oud     = '2026-11-20 15:03:11 +01:00 | veld: name (text) | reden: onleesbare tekst | waarde: xkqzvbnmwrt';
$gelezen = mediamora_antispam_lees_regel( $oud );
$test( 'oude regel: zonder formulier en kenmerken', null === $gelezen['formulier'] && null === $gelezen['kenmerken'] && 'xkqzvbnmwrt' === $gelezen['waarde'] );
$test( 'onleesbare regel', null === mediamora_antispam_lees_regel( 'geen logregel' ) );

/* -------------------------------------------------------------------------
 * Lijkt op een mens
 * ---------------------------------------------------------------------- */

$goed = array( 'mail' => 'geldig', 'links' => 0, 'latijn' => true );

$test( 'constanten: nooit menselijk staat niet in de menselijke redenen', array() === array_intersect( MEDIAMORA_ANTISPAM_NOOIT_MENSELIJK, MEDIAMORA_ANTISPAM_MENSELIJKE_REDENEN ) );

foreach ( array( 'underscore in naamveld', 'cijferreeks in naamveld', 'domeinnaam in naamveld', 'onleesbare tekst', 'herhaalde trefwoorden' ) as $reden ) {
	$test( 'menselijk: ' . $reden, mediamora_antispam_lijkt_menselijk( array( $reden ), $goed ) );
}
$test( 'menselijk: twee menselijke redenen', mediamora_antispam_lijkt_menselijk( array( 'onleesbare tekst', 'herhaalde trefwoorden' ), $goed ) );

foreach ( MEDIAMORA_ANTISPAM_NOOIT_MENSELIJK as $reden ) {
	$test( 'nooit menselijk: ' . $reden, ! mediamora_antispam_lijkt_menselijk( array( $reden ), $goed ) );
}
$test( 'nooit menselijk, ook samen met onleesbare tekst', ! mediamora_antispam_lijkt_menselijk( array( 'onleesbare tekst', 'verkorte URL' ), $goed ) );
foreach ( array( 'verdachte link', 'bericht is alleen een link', 'noemt eigen domeinnaam', 'dubbele inhoud' ) as $reden ) {
	$test( 'niet menselijk: ' . $reden, ! mediamora_antispam_lijkt_menselijk( array( $reden ), $goed ) );
}

$test( 'niet menselijk: ongeldig e-mailadres', ! mediamora_antispam_lijkt_menselijk( array( 'onleesbare tekst' ), array( 'mail' => 'ongeldig' ) + $goed ) );
$test( 'niet menselijk: geen e-mailadres', ! mediamora_antispam_lijkt_menselijk( array( 'onleesbare tekst' ), array( 'mail' => 'geen' ) + $goed ) );
$test( 'niet menselijk: een link', ! mediamora_antispam_lijkt_menselijk( array( 'onleesbare tekst' ), array( 'links' => 1 ) + $goed ) );
$test( 'niet menselijk: niet-Latijns', ! mediamora_antispam_lijkt_menselijk( array( 'onleesbare tekst' ), array( 'latijn' => false ) + $goed ) );

$test( 'postcode alleen: niet menselijk, telt apart', ! mediamora_antispam_lijkt_menselijk( array( 'ongeldige postcode' ), $goed ) );
$test( 'postcode en onleesbare tekst: menselijk', mediamora_antispam_lijkt_menselijk( array( 'ongeldige postcode', 'onleesbare tekst' ), $goed ) );

// Echte inzendingen uit de bestaande tests, via de validatie.
insturen( 'NAYUYUTY1354024NEWETREWT' );
$r = mediamora_antispam_lees_regel( laatste_regel() );
$test( 'NAYUYUTY1354024NEWETREWT met geldig adres: menselijk', 'cijferreeks in naamveld' === $r['reden'] && mediamora_antispam_lijkt_menselijk( array( $r['reden'] ), $r['kenmerken'] ) );
insturen( 'onhour.ru' );
$r = mediamora_antispam_lees_regel( laatste_regel() );
$test( 'onhour.ru: domein in de naam, maar een link: niet menselijk', 'domeinnaam in naamveld' === $r['reden'] && ! mediamora_antispam_lijkt_menselijk( array( $r['reden'] ), $r['kenmerken'] ) );

// Regels van voor 1.34.0: alleen de waarde.
$test( 'oud: onleesbare tekst zonder link: menselijk', mediamora_antispam_lijkt_menselijk( array( 'onleesbare tekst' ), null, array( 'xkqzvbnmwrt' ) ) );
$test( 'oud: met een link: niet menselijk', ! mediamora_antispam_lijkt_menselijk( array( 'onleesbare tekst' ), null, array( 'kijk op https://een.ru' ) ) );
$test( 'oud: niet-Latijns: niet menselijk', ! mediamora_antispam_lijkt_menselijk( array( 'onleesbare tekst' ), null, array( 'Привет' ) ) );

/* -------------------------------------------------------------------------
 * De samenvatting
 * ---------------------------------------------------------------------- */

$nu = time();
$l  = array();
$k  = ' | kenmerken: mail=geldig links=0 latijn=ja';

// Twaalf menselijke, een per uur; de nieuwste eerst verwacht.
for ( $i = 1; $i <= 12; $i++ ) {
	$l[] = mediamora_antispam_log_time( $nu - $i * HOUR_IN_SECONDS ) . ' | veld: name (text) | reden: underscore in naamveld | waarde: GEHEIM' . $i . '_x | formulier: Contact (abc1234)' . $k;
}
// Eén inzending met twee regels (dubbele inhoud): één weigering.
$l[] = mediamora_antispam_log_time( $nu - 2 * DAY_IN_SECONDS ) . ' | veld: a (n.v.t.) | reden: dubbele inhoud | waarde: GEHEIM lang | formulier: Offerte (x1)' . $k;
$l[] = mediamora_antispam_log_time( $nu - 2 * DAY_IN_SECONDS ) . ' | veld: b (n.v.t.) | reden: dubbele inhoud | waarde: GEHEIM lang | formulier: Offerte (x1)' . $k;
// Postcode.
$l[] = mediamora_antispam_log_time( $nu - 3 * DAY_IN_SECONDS ) . ' | veld: postcode (text) | reden: ongeldige postcode | waarde: 122551 | formulier: Contact (abc1234)' . $k;
// Referrer.
$l[] = mediamora_antispam_log_time( $nu - 4 * DAY_IN_SECONDS ) . ' | veld: referrer (n.v.t.) | reden: verstuurd zonder JavaScript (geen referrer) | waarde: formulier: Contact (abc1234), referer_title: Contact | formulier: Contact (abc1234) | kenmerken: -';
// Van voor 1.34.0, binnen de periode.
$l[] = mediamora_antispam_log_time( $nu - 5 * DAY_IN_SECONDS ) . ' | veld: message (textarea) | reden: onleesbare tekst | waarde: xkqzvbnmwrt';
// Buiten de periode.
$l[] = mediamora_antispam_log_time( $nu - 31 * DAY_IN_SECONDS ) . ' | veld: name (text) | reden: onleesbare tekst | waarde: GEHEIM oud | formulier: Contact (abc1234)' . $k;

file_put_contents( mediamora_antispam_log_file(), implode( "\n", $l ) . "\n" );

$s    = mediamora_antispam_samenvatting( $nu );
$json = json_encode( $s );

$test( 'sleutels', array( 'periode', 'totaal', 'menselijk', 'postcode', 'menselijke' ) === array_keys( $s ) );
$test( 'periode van dertig dagen', 30 === $s['periode']['dagen'] && wp_date( DATE_ATOM, $nu ) === $s['periode']['tot'] && wp_date( DATE_ATOM, $nu - 30 * DAY_IN_SECONDS ) === $s['periode']['van'] );
$test( 'totaal: 12 + 1 + 1 + 1 + 1, buiten de periode niet (' . $s['totaal'] . ')', 16 === $s['totaal'] );
$test( 'menselijk: 12 en de oude regel (' . $s['menselijk'] . ')', 13 === $s['menselijk'] );
$test( 'postcode apart (' . $s['postcode'] . ')', 1 === $s['postcode'] );
$test( 'hooguit tien in de lijst', 10 === count( $s['menselijke'] ) );
$test( 'per weigering alleen tijd, formulier en reden', array( 'tijd', 'formulier', 'reden' ) === array_keys( $s['menselijke'][0] ) );
$test( 'nieuwste eerst', wp_date( DATE_ATOM, $nu - HOUR_IN_SECONDS ) === $s['menselijke'][0]['tijd'] && wp_date( DATE_ATOM, $nu - 10 * HOUR_IN_SECONDS ) === $s['menselijke'][9]['tijd'] );
$test( 'formulier en reden', 'Contact (abc1234)' === $s['menselijke'][0]['formulier'] && 'underscore in naamveld' === $s['menselijke'][0]['reden'] );
$test( 'geen ingevulde waarden in de samenvatting', false === strpos( $json, 'GEHEIM' ) && false === strpos( $json, '122551' ) && false === strpos( $json, 'xkqzvbnmwrt' ) );

// Zonder log.
unlink( mediamora_antispam_log_file() );
$s = mediamora_antispam_samenvatting( $nu );
$test( 'zonder log: alles nul', 0 === $s['totaal'] && 0 === $s['menselijk'] && 0 === $s['postcode'] && array() === $s['menselijke'] );

printf( "%d fout(en)\n", $fout );
exit( $fout ? 1 : 0 );
