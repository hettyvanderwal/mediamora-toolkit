<?php
/**
 * Mediamora Toolkit, testgevallen voor de maandelijkse formuliertest van de
 * Formuliermonitor (1.34.0): het vaste moment per domein, het inplannen en
 * het vangnet, welke formulieren meedoen, dat alleen de mailacties draaien
 * en hun mail niet de deur uit gaat, de mailroute, elke uitkomst, de melding
 * alleen bij een fout, dat de anti-spam, Inzendingen en de Formuliermonitor
 * er niets van merken, en de sync zonder ingevulde waarden.
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/formuliertest.php
 * Geeft exitcode 1 als er een geval misgaat.
 *
 * Elementor Pro is nagebootst in tests/hulp/elementor-pro-nep.php. De
 * melding gaat met mail() van de Formuliermonitor; die wordt opgevangen zoals
 * in tests/formuliermonitor-hersteld.php, met een sendmail_path dat naar
 * ditzelfde bestand wijst (deel "vang"). wp_mail() is nagebootst zoals in
 * WordPress 6.x: eerst het filter wp_mail, dan pre_wp_mail, dan
 * phpmailer_init, en wp_mail_failed of wp_mail_succeeded.
 *
 * MM_FORMULIERTEST_ONTVANGER is een constante. De gevallen zonder en met
 * hetzelfde adres als MM_MONITOR_ONTVANGER draaien daarom in een eigen proces.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$deel = $argv[1] ?? 'start';

if ( 'vang' === $deel ) {
	$map    = $argv[2];
	$nummer = count( glob( $map . '/mail-*.txt' ) ) + 1;
	file_put_contents( sprintf( '%s/mail-%03d.txt', $map, $nummer ), file_get_contents( 'php://stdin' ) );
	exit( 0 );
}

if ( 'start' === $deel ) {
	$code = 0;
	foreach ( array( 'binnen', 'zonder', 'gelijk' ) as $onderdeel ) {
		$map = sys_get_temp_dir() . '/mm-formuliertest-' . getmypid() . '-' . $onderdeel;
		@mkdir( $map . '/uploads/mediamora-antispam-testtest1234', 0777, true );

		$vanger = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' vang ' . escapeshellarg( $map );
		passthru(
			escapeshellarg( PHP_BINARY ) . ' -d ' . escapeshellarg( 'sendmail_path=' . $vanger )
				. ' ' . escapeshellarg( __FILE__ ) . ' ' . $onderdeel . ' ' . escapeshellarg( $map ),
			$uit
		);
		$code = $code || $uit;

		array_map( 'unlink', array_filter( glob( $map . '/{,uploads/mediamora-antispam-testtest1234/}{*,.htaccess}', GLOB_BRACE ) ?: array(), 'is_file' ) );
		@rmdir( $map . '/uploads/mediamora-antispam-testtest1234' );
		@rmdir( $map . '/uploads' );
		@rmdir( $map );
	}
	exit( $code ? 1 : 0 );
}

/* -------------------------------------------------------------------------
 * Nagebootste WordPress-functies
 * ---------------------------------------------------------------------- */

$GLOBALS['map'] = $argv[2];

date_default_timezone_set( 'UTC' );

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WP_CONTENT_DIR', $GLOBALS['map'] );
define( 'MM_MONITOR_ONTVANGER', 'hetty@mediamora.nl' );
define( 'MM_TOOLKIT_VERSIE', '1.34.0' );
define( 'MAINWP_CHILD_FILE', __FILE__ );

if ( 'binnen' === $deel ) {
	define( 'MM_FORMULIERTEST_ONTVANGER', 'formuliertest@mediamora.nl' );
}
if ( 'gelijk' === $deel ) {
	define( 'MM_FORMULIERTEST_ONTVANGER', 'Hetty@Mediamora.nl' );
}

$GLOBALS['hooks']     = array();
$GLOBALS['opties']    = array( 'mediamora_antispam_log_dir' => 'mediamora-antispam-testtest1234' );
$GLOBALS['cron']      = array();
$GLOBALS['meta']      = array();
$GLOBALS['home']      = 'https://www.voorbeeld.nl';
$GLOBALS['aangeboden'] = array(); // mails die wp_mail() echt zou versturen
$GLOBALS['gewist']    = 0;       // keer dat phpmailer_init de ontvangers wiste

function add_filter( $hook, $callback, $prioriteit = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][ $prioriteit ][] = $callback;
	return true;
}
function add_action( $hook, $callback, $prioriteit = 10, $args = 1 ) {
	return add_filter( $hook, $callback, $prioriteit, $args );
}
function remove_filter( $hook, $callback, $prioriteit = 10 ) {
	foreach ( $GLOBALS['hooks'][ $hook ][ $prioriteit ] ?? array() as $i => $cb ) {
		if ( $cb === $callback ) {
			unset( $GLOBALS['hooks'][ $hook ][ $prioriteit ][ $i ] );
			return true;
		}
	}
	return false;
}
function remove_action( $hook, $callback, $prioriteit = 10 ) {
	return remove_filter( $hook, $callback, $prioriteit );
}
function has_filter( $hook ) {
	foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $lijst ) {
		if ( $lijst ) {
			return true;
		}
	}
	return false;
}
function apply_filters( $hook, $waarde, ...$rest ) {
	$lijst = $GLOBALS['hooks'][ $hook ] ?? array();
	ksort( $lijst );
	foreach ( $lijst as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$waarde = call_user_func( $callback, $waarde, ...$rest );
		}
	}
	return $waarde;
}
function do_action( $hook, ...$args ) {
	$lijst = $GLOBALS['hooks'][ $hook ] ?? array();
	ksort( $lijst );
	foreach ( $lijst as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			call_user_func_array( $callback, $args );
		}
	}
}
function get_option( $naam, $standaard = false ) {
	return array_key_exists( $naam, $GLOBALS['opties'] ) ? $GLOBALS['opties'][ $naam ] : $standaard;
}
function update_option( $naam, $waarde, $autoload = null ) {
	$GLOBALS['opties'][ $naam ] = $waarde;
	return true;
}
function add_option( $naam, $waarde, $leeg = '', $autoload = null ) {
	if ( array_key_exists( $naam, $GLOBALS['opties'] ) ) {
		return false;
	}
	$GLOBALS['opties'][ $naam ] = $waarde;
	return true;
}
function delete_option( $naam ) {
	if ( ! array_key_exists( $naam, $GLOBALS['opties'] ) ) {
		return false;
	}
	unset( $GLOBALS['opties'][ $naam ] );
	return true;
}
function wp_next_scheduled( $hook ) {
	return $GLOBALS['cron'][ $hook ] ?? false;
}
function wp_schedule_single_event( $tijd, $hook ) {
	$GLOBALS['cron'][ $hook ] = $tijd;
	return true;
}
function wp_schedule_event( $tijd, $herhaling, $hook ) {
	return wp_schedule_single_event( $tijd, $hook );
}
function wp_clear_scheduled_hook( $hook ) {
	unset( $GLOBALS['cron'][ $hook ] );
	return 1;
}
function wp_unschedule_hook( $hook ) {
	return wp_clear_scheduled_hook( $hook );
}
function current_time( $formaat ) {
	return wp_date( $formaat );
}
// Zoals WordPress met de tijdzone Europe/Amsterdam.
function wp_date( $formaat, $tijd = null ) {
	$datum = new DateTime( '@' . ( null === $tijd ? time() : $tijd ) );
	$datum->setTimezone( wp_timezone() );
	return $datum->format( $formaat );
}
function wp_timezone() {
	return new DateTimeZone( 'Europe/Amsterdam' );
}
function wp_timezone_string() {
	return 'Europe/Amsterdam';
}
function home_url( $pad = '' ) {
	return $GLOBALS['home'] . $pad;
}
function wp_parse_url( $url, $deel = -1 ) {
	return parse_url( $url, $deel );
}
function is_wp_error( $ding ) {
	return $ding instanceof WP_Error;
}
function absint( $getal ) {
	return abs( (int) $getal );
}
function is_email( $adres ) {
	return false !== filter_var( $adres, FILTER_VALIDATE_EMAIL );
}
function sanitize_email( $adres ) {
	return trim( $adres );
}
function wp_strip_all_tags( $tekst ) {
	return trim( strip_tags( $tekst ) );
}
function wp_unslash( $waarde ) {
	return is_string( $waarde ) ? stripslashes( $waarde ) : $waarde;
}
function wp_parse_args( $args, $standaard ) {
	return array_merge( $standaard, (array) $args );
}
function wp_mkdir_p( $map ) {
	return is_dir( $map ) || @mkdir( $map, 0777, true );
}
function wp_doing_ajax() {
	return false; // de test draait in WP-cron, niet in admin-ajax
}
function __( $tekst ) {
	return $tekst;
}
function get_post_meta( $id, $sleutel, $enkel = false ) {
	return $GLOBALS['meta'][ $id ][ $sleutel ] ?? '';
}
function get_the_title( $id ) {
	return $GLOBALS['titels'][ $id ] ?? '';
}
function get_permalink( $id ) {
	return $GLOBALS['home'] . '/?p=' . $id;
}

class WP_Error {
	private $code;
	private $melding;
	public function __construct( $code, $melding ) {
		$this->code    = $code;
		$this->melding = $melding;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->melding;
	}
	public function get_error_data() {
		return array();
	}
}

class Nep_Mailer {
	public $ontvangers;
	public function __construct( $ontvangers ) {
		$this->ontvangers = $ontvangers;
	}
	public function clearAllRecipients() {
		$this->ontvangers = array();
		$GLOBALS['gewist']++;
	}
}

/**
 * wp_mail() zoals in WordPress 6.x. Met negeer_pre_wp_mail gedraagt hij zich
 * als een plugin die wp_mail() vervangt en pre_wp_mail overslaat.
 */
function wp_mail( $to, $subject, $message, $headers = '', $attachments = array() ) {
	$atts = apply_filters( 'wp_mail', compact( 'to', 'subject', 'message', 'headers', 'attachments' ) );

	if ( empty( $GLOBALS['negeer_pre_wp_mail'] ) ) {
		$pre = apply_filters( 'pre_wp_mail', null, $atts );
		if ( null !== $pre ) {
			return $pre;
		}
	}

	$adressen = is_array( $atts['to'] ) ? $atts['to'] : array_filter( explode( ',', (string) $atts['to'] ) );
	$koppen   = is_array( $atts['headers'] ) ? $atts['headers'] : preg_split( '/\r\n|\n/', (string) $atts['headers'] );
	foreach ( $koppen as $kop ) {
		if ( preg_match( '/^\s*b?cc\s*:\s*(.+)$/i', (string) $kop, $m ) ) {
			$adressen[] = trim( $m[1] );
		}
	}

	$GLOBALS['na_filters'][] = array_values( $adressen );

	$mailer = new Nep_Mailer( array_values( $adressen ) );
	do_action( 'phpmailer_init', $mailer );

	if ( $mailer->ontvangers ) {
		$GLOBALS['aangeboden'][] = array(
			'to'      => $mailer->ontvangers,
			'subject' => $atts['subject'],
			'headers' => $koppen,
		);
	}

	if ( ! empty( $GLOBALS['mail_faalt'] ) || ! $mailer->ontvangers ) {
		do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'SMTP Error: Could not authenticate info@host.nl' ) );
		return false;
	}

	do_action( 'wp_mail_succeeded', $atts );
	return true;
}

class Nep_Wpdb {
	public $posts    = 'wp_posts';
	public $postmeta = 'wp_postmeta';
	public function prepare( $query, ...$args ) {
		return $query;
	}
	public function esc_like( $tekst ) {
		return addcslashes( $tekst, '_%\\' );
	}
	public function get_col( $query ) {
		return $GLOBALS['nep_paginas'];
	}
	public function get_results( $query ) {
		return $GLOBALS['nep_templates'];
	}
	public function get_var( $query ) {
		return null;
	}
}
$GLOBALS['wpdb'] = new Nep_Wpdb();

// Uit mediamora-toolkit.php.
function mm_toolkit_status() {
	return array(
		'formuliermonitor' => array( 'laden' => true ),
		'anti_spam'        => array( 'laden' => true ),
	);
}
function mm_toolkit_huidige_versie() {
	return '1.34.0';
}
function mm_toolkit_onderhoudsklant() {
	return true;
}

require __DIR__ . '/hulp/elementor-pro-nep.php';
require dirname( __DIR__ ) . '/modules/anti-spam.php';
require dirname( __DIR__ ) . '/modules/formuliermonitor.php';
require dirname( __DIR__ ) . '/includes/mainwp.php';

$GLOBALS['nep_elementor'] = new Nep_Elementor();

$fout   = 0;
$totaal = 0;
function klopt( $uitkomst, $verwacht, $wat ) {
	$GLOBALS['totaal']++;
	if ( $uitkomst !== $verwacht ) {
		$GLOBALS['fout']++;
		printf( "FOUT  %s\n      gaf      %s\n      verwacht %s\n", $wat, var_export( $uitkomst, true ), var_export( $verwacht, true ) );
	}
}
function vangmails() {
	return glob( $GLOBALS['map'] . '/mail-*.txt' );
}
function klaar() {
	printf( "%d van %d gevallen goed (%s)\n", $GLOBALS['totaal'] - $GLOBALS['fout'], $GLOBALS['totaal'], $GLOBALS['argv'][1] );
	exit( $GLOBALS['fout'] ? 1 : 0 );
}

/* -------------------------------------------------------------------------
 * De formulieren op de nagebootste site
 * ---------------------------------------------------------------------- */

function veld( $id, $type, $label, $extra = array() ) {
	return array_merge(
		array(
			'custom_id'   => $id,
			'field_type'  => $type,
			'field_label' => $label,
		),
		$extra
	);
}

function formulier( $id, $naam, $velden, $extra = array() ) {
	return array(
		'id'         => $id,
		'elType'     => 'widget',
		'widgetType' => 'form',
		'settings'   => array_merge(
			array(
				'form_name'   => $naam,
				'form_fields' => $velden,
				'email_to'    => 'info@voorbeeld.nl',
			),
			$extra
		),
	);
}

function container( $elementen ) {
	static $n = 0;
	$n++;
	return array(
		'id'       => 'c' . $n,
		'elType'   => 'container',
		'elements' => $elementen,
	);
}

$eenvoudig = array(
	veld( 'name', 'text', 'Naam', array( 'required' => 'true' ) ),
	veld( 'email', 'email', 'E-mail', array( 'required' => 'true' ) ),
	veld( 'message', 'textarea', 'Bericht' ),
);

$alles = array(
	veld( 'name', 'text', 'Naam', array( 'required' => 'true' ) ),
	veld( 'email', 'email', 'E-mailadres', array( 'required' => 'true' ) ),
	veld( 'telefoon', 'tel', 'Telefoon' ),
	veld( 'postcode', 'text', 'Postcode' ),
	veld( 'plaats', 'text', 'Woonplaats' ),
	veld( 'bedrijf', 'text', '', array( 'placeholder' => 'Bedrijf' ) ),
	veld( 'message', 'textarea', 'Bericht', array( 'required' => 'true' ) ),
	veld( 'onderwerp', 'select', 'Onderwerp', array( 'required' => 'true', 'field_options' => "Kies een onderwerp|\nOfferte\nVraag" ) ),
	veld( 'voorkeur', 'radio', 'Voorkeur', array( 'field_options' => "Ochtend|ochtend\nMiddag|middag" ) ),
	veld( 'interesses', 'checkbox', 'Interesses', array( 'field_options' => "Websites\nOnderhoud" ) ),
	veld( 'akkoord', 'acceptance', 'Akkoord', array( 'required' => 'true' ) ),
	veld( 'datum', 'date', 'Datum' ),
	veld( 'tijd', 'time', 'Tijd' ),
	veld( 'aantal', 'number', 'Aantal', array( 'field_min' => '2' ) ),
	veld( 'website', 'url', 'Website' ),
	veld( 'bron', 'hidden', 'Bron', array( 'field_value' => 'site' ) ),
	veld( 'hp', 'honeypot', '' ),
	veld( 'stap', 'step', 'Stap 2' ),
	veld( 'bijlage', 'upload', 'Bijlage' ),
	veld( 'tekst', 'html', '' ),
);

$d = $GLOBALS['nep_elementor']->documents;

// Popup met weergavevoorwaarden.
$d->data[70] = array( container( array( formulier( 'popupform', 'Nieuwsbrief', $eenvoudig ) ) ) );
$GLOBALS['meta'][70]['_elementor_conditions'] = array( 'include/general' );

// Contact: alle acties aan, Cc en Bcc, Email 2 naar de bezoeker. Plus een
// globale widget en een sectietemplate via de widget Template.
$d->data[10] = array(
	container(
		array(
			formulier(
				'f1',
				'Contact',
				$alles,
				array(
					'submit_actions' => array( 'email', 'email2', 'webhook', 'redirect', 'mailerlite', 'activecampaign', 'save-to-database', 'popup' ),
					'email_to_cc'    => 'cc@voorbeeld.nl',
					'email_to_bcc'   => 'bcc@voorbeeld.nl',
					'email_to_2'     => '[field id="email"]',
				)
			),
			array( 'id' => 'g1', 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 50 ),
			array( 'id' => 't1', 'elType' => 'widget', 'widgetType' => 'template', 'settings' => array( 'template_id' => 60 ) ),
		)
	),
);
// Over ons: dezelfde globale widget en hetzelfde template nog een keer.
$d->data[11] = array(
	container(
		array(
			array( 'id' => 'g2', 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => 50 ),
			array( 'id' => 't2', 'elType' => 'widget', 'widgetType' => 'template', 'settings' => array( 'template_id' => 60 ) ),
		)
	),
);
$d->data[50]                                         = array( formulier( 'gform', 'Offerte', $eenvoudig ) );
$GLOBALS['meta'][50]['_elementor_template_widget_type'] = 'form';
// Zonder submit_actions in de opgeslagen gegevens: de standaard, email.
$d->data[60] = array( container( array( formulier( 'sform', 'Terugbellen', $eenvoudig ) ) ) );

$d->data[12] = array( formulier( 'up', 'Sollicitatie', array_merge( $eenvoudig, array( veld( 'cv', 'upload', 'Cv', array( 'required' => 'true' ) ) ) ) ) );
$d->data[13] = array( formulier( 'cap', 'Met captcha', array_merge( $eenvoudig, array( veld( 'rc', 'recaptcha_v3', '' ) ) ) ) );
$d->data[14] = array( formulier( 'wh', 'Alleen webhook', $eenvoudig, array( 'submit_actions' => array( 'webhook' ) ) ) );
$d->data[15] = array( formulier( 'strikt', 'Strikt', array_merge( $eenvoudig, array( veld( 'tel', 'tel', 'Telefoon' ) ) ) ) );
$d->data[16] = array( formulier( 'adres', 'Fout adres', $eenvoudig, array( 'email_to' => 'geen-adres' ) ) );
$d->data[17] = array( formulier( 'exc', 'Exception', $eenvoudig, array( 'nep_exception' => true ) ) );
$d->data[18] = array( formulier( 'onb', 'Onbekend veld', array_merge( $eenvoudig, array( veld( 'sig', 'signature', 'Handtekening', array( 'required' => 'true' ) ) ) ) ) );

$GLOBALS['titels'] = array(
	10 => 'Contact',
	11 => 'Over ons',
	12 => 'Werken bij',
	13 => 'Captcha',
	14 => 'Webhook',
	15 => 'Strikt',
	16 => 'Fout adres',
	17 => 'Exception',
	18 => 'Onbekend',
);
$GLOBALS['nep_templates'] = array( (object) array( 'ID' => 70, 'post_title' => 'Nieuwsbrief-popup', 'soort' => 'popup' ) );
$GLOBALS['nep_paginas']   = array( 10, 11, 12, 13, 14, 15, 16, 17 );

// Een strengere telefooncheck van een andere plugin, alleen op Strikt.
add_action(
	'elementor_pro/forms/validation/tel',
	function ( $field, $record, $handler ) {
		if ( 'Strikt' === $record->get_form_settings( 'form_name' ) ) {
			$handler->add_error( $field['id'], 'Alleen internationale nummers.' );
		}
	},
	10,
	3
);

// Wat de module Nieuwsbrief alleen bij vinkje en de koppelingen gebruiken.
// Mag tijdens de test niet aangeroepen worden.
$GLOBALS['nep_filters'] = array();
foreach ( array( 'elementor_pro/forms/submit_actions', 'elementor_pro/forms/record/actions_before', 'elementor_pro/forms/new_record', 'elementor_pro/forms/actions/after_run' ) as $hook ) {
	add_filter(
		$hook,
		function ( $waarde ) use ( $hook ) {
			$GLOBALS['nep_filters'][] = $hook;
			return $waarde;
		}
	);
}

/* -------------------------------------------------------------------------
 * Delen zonder of met dezelfde ontvanger: alleen de mailroute
 * ---------------------------------------------------------------------- */

if ( 'zonder' === $deel || 'gelijk' === $deel ) {
	$GLOBALS['nep_paginas']   = array();
	$GLOBALS['nep_templates'] = array();

	$ronde = mm_formuliertest_ronde( false );
	klopt( $ronde['mailroute']['uitkomst'], 'niet_getest', $deel . ': mailroute niet getest' );
	klopt(
		$ronde['mailroute']['melding'],
		'zonder' === $deel ? 'MM_FORMULIERTEST_ONTVANGER is niet gezet' : 'MM_FORMULIERTEST_ONTVANGER is gelijk aan MM_MONITOR_ONTVANGER',
		$deel . ': reden vastgelegd'
	);
	klopt( $GLOBALS['aangeboden'], array(), $deel . ': geen mail via wp_mail' );
	klopt( $ronde['totaal'], 'niet_getest', $deel . ': totaal niet getest' );
	klopt( vangmails(), array(), $deel . ': geen melding' );
	klaar();
}

/* -------------------------------------------------------------------------
 * 1. Het vaste moment
 * ---------------------------------------------------------------------- */

$v = mm_formuliertest_verschuiving( 'voorbeeld.nl' );
klopt( $v, mm_formuliertest_verschuiving( 'voorbeeld.nl' ), '1: vast per domein' );
klopt( $v, mm_formuliertest_verschuiving( 'Voorbeeld.NL' ), '1: hoofdletters maken niet uit' );

$dagen    = array( 0, 0, 0 );
$waarden  = array();
$binnen   = true;
for ( $i = 0; $i < 200; $i++ ) {
	$x         = mm_formuliertest_verschuiving( 'klant' . $i . '.nl' );
	$binnen    = $binnen && $x >= 0 && $x < 72 * 3600;
	$waarden[] = $x;
	$dagen[ min( 2, intdiv( $x, 86400 ) ) ]++;
}
klopt( $binnen, true, '1: 200 domeinen allemaal binnen 72 uur' );
klopt( count( array_unique( $waarden ) ) > 190, true, '1: 200 domeinen vrijwel allemaal een eigen moment' );
klopt( min( $dagen ) >= 40, true, '1: verdeeld over de drie dagen (' . implode( '/', $dagen ) . ')' );

$begin = ( new DateTimeImmutable( '2026-12-01 00:00:00', wp_timezone() ) )->getTimestamp();
$m     = mm_formuliertest_moment( 2026, 12, 'voorbeeld.nl' );
klopt( $m - $begin, $v, '1: moment is begin van de maand (tijdzone site) plus de verschuiving' );
klopt( mm_formuliertest_volgende( $m - 1, 'voorbeeld.nl' ), $m, '1: vlak voor het moment: dit moment' );
klopt( mm_formuliertest_volgende( $m, 'voorbeeld.nl' ), mm_formuliertest_moment( 2027, 1, 'voorbeeld.nl' ), '1: op het moment: de maand erna, ook over de jaargrens' );
klopt( mm_formuliertest_huidige_ronde( $m - 1, 'voorbeeld.nl' ), '2026-11', '1: vlak voor het moment hoort de ronde bij de vorige maand' );
klopt( mm_formuliertest_huidige_ronde( $m, 'voorbeeld.nl' ), '2026-12', '1: vanaf het moment bij deze maand' );
klopt( mm_formuliertest_huidige_ronde( mm_formuliertest_moment( 2027, 1, 'voorbeeld.nl' ) - 1, 'voorbeeld.nl' ), '2026-12', '1: januari voor het moment: december' );

/* -------------------------------------------------------------------------
 * 2. Inplannen, vangnet en handmatig
 * ---------------------------------------------------------------------- */

$nu = time();
mm_formuliertest_inplannen();
klopt( get_option( MM_FORMULIERTEST_MAAND ), mm_formuliertest_huidige_ronde( $nu, 'voorbeeld.nl' ), '2: eerste keer: ronde van nu telt als gedaan' );
klopt( wp_next_scheduled( MM_FORMULIERTEST_HOOK ), mm_formuliertest_volgende( $nu, 'voorbeeld.nl' ), '2: event op het volgende vaste moment' );
klopt( get_option( MM_FORMULIERTEST_OPTIE ), false, '2: inplannen draait geen ronde' );

$GLOBALS['opties'][ MM_FORMULIERTEST_MAAND ] = '2000-01';
mm_formuliertest_inplannen();
klopt( wp_next_scheduled( MM_FORMULIERTEST_HOOK ) <= time(), true, '2: vangnet: ronde gemist, event zo snel mogelijk' );

$GLOBALS['nep_paginas']   = array( 10 );
$GLOBALS['nep_templates'] = array();
mm_formuliertest_maandelijks();
klopt( get_option( MM_FORMULIERTEST_MAAND ), mm_formuliertest_huidige_ronde( time(), 'voorbeeld.nl' ), '2: na de ronde is deze maand gedaan' );
klopt( wp_next_scheduled( MM_FORMULIERTEST_HOOK ), mm_formuliertest_volgende( time(), 'voorbeeld.nl' ), '2: na de ronde het volgende vaste moment' );
klopt( get_option( MM_FORMULIERTEST_OPTIE )['handmatig'], false, '2: maandelijkse ronde niet handmatig' );

$voor_event = wp_next_scheduled( MM_FORMULIERTEST_HOOK );
$voor_maand = get_option( MM_FORMULIERTEST_MAAND );
mm_formuliertest_maandelijks();
klopt( get_option( MM_FORMULIERTEST_OPTIE )['handmatig'], false, '2: tweede keer in dezelfde maand: geen nieuwe ronde' );
$tijd_voor = get_option( MM_FORMULIERTEST_OPTIE )['tijd'];

$ronde = mm_formuliertest_ronde( true );
klopt( $ronde['handmatig'], true, '2: handmatig vastgelegd' );
klopt( array( wp_next_scheduled( MM_FORMULIERTEST_HOOK ), get_option( MM_FORMULIERTEST_MAAND ) ), array( $voor_event, $voor_maand ), '2: handmatig verschuift het vaste moment niet' );
klopt( get_option( MM_FORMULIERTEST_SLOT ), false, '2: slot weer vrij' );

$GLOBALS['opties'][ MM_FORMULIERTEST_SLOT ] = time();
klopt( mm_formuliertest_ronde( true ), null, '2: tweede ronde tegelijk loopt niet' );
unset( $GLOBALS['opties'][ MM_FORMULIERTEST_SLOT ] );

/* -------------------------------------------------------------------------
 * 3. Formulieren vinden
 * ---------------------------------------------------------------------- */

$GLOBALS['nep_paginas']   = array( 10, 11, 12, 13, 14, 15, 16, 17 );
$GLOBALS['nep_templates'] = array( (object) array( 'ID' => 70, 'post_title' => 'Nieuwsbrief-popup', 'soort' => 'popup' ) );

$k = mm_formuliertest_formulieren();
klopt(
	array_column( $k, 'sleutel' ),
	array( '70:popupform', '10:f1', 'global:50', '60:sform', '12:up', '13:cap', '15:strikt', '16:adres', '17:exc' ),
	'3: templates eerst, dubbele één keer, zonder mailactie niet'
);
klopt( array( $k[2]['post_id'], $k[2]['form_id'], $k[2]['pagina'] ), array( 10, 'g1', 'Contact' ), '3: globale widget met de pagina als post_id, zoals Elementor hem instuurt' );
klopt( array( $k[3]['post_id'], $k[3]['queried_id'], $k[3]['pagina'] ), array( 60, 10, 'Contact' ), '3: template via de widget Template met het template als post_id' );
klopt( array( $k[0]['pagina'], $k[0]['url'] ), array( 'Nieuwsbrief-popup (popup)', 'https://www.voorbeeld.nl/' ), '3: popup met titel en de voorpagina' );

$GLOBALS['meta'][70]['_elementor_conditions'] = array();
klopt( array_column( mm_formuliertest_formulieren(), 'sleutel' )[0], '10:f1', '3: popup zonder voorwaarden en niet geopend: niet in gebruik' );
$GLOBALS['meta'][70]['_elementor_conditions'] = array( 'include/general' );

$veel = array();
for ( $i = 0; $i < 15; $i++ ) {
	$veel[] = formulier( 'v' . $i, 'Veel ' . $i, $eenvoudig );
}
$gevonden = array();
mm_formuliertest_zoek( array( container( $veel ) ), 99, array( 'pagina' => 'Veel', 'url' => '', 'queried_id' => 99 ), $gevonden );
klopt( count( $gevonden ), 10, '3: hooguit 10 per site' );

$kring = array();
$d->data[80] = array( array( 'id' => 'tk', 'elType' => 'widget', 'widgetType' => 'template', 'settings' => array( 'template_id' => 81 ) ) );
$d->data[81] = array( array( 'id' => 'tk2', 'elType' => 'widget', 'widgetType' => 'template', 'settings' => array( 'template_id' => 80 ) ) );
mm_formuliertest_zoek( $d->data[80], 80, array( 'pagina' => 'Kring', 'url' => '', 'queried_id' => 80 ), $kring );
klopt( $kring, array(), '3: templates die naar elkaar verwijzen: geen eindeloze lus' );

/* -------------------------------------------------------------------------
 * 4. De testwaarden
 * ---------------------------------------------------------------------- */

$w = mm_formuliertest_waarden( $alles );
klopt(
	$w,
	array(
		'name'       => 'Hetty',
		'email'      => 'hetty@mediamora.nl',
		'telefoon'   => '0612345678',
		'postcode'   => '9700 AA',
		'plaats'     => 'Groningen',
		'bedrijf'    => 'test',
		'message'    => 'Automatische maandelijkse test van Mediamora, niet beantwoorden.',
		'onderwerp'  => 'Offerte',
		'voorkeur'   => 'ochtend',
		'interesses' => array( 'Websites' ),
		'akkoord'    => 'on',
		'datum'      => wp_date( 'Y-m-d' ),
		'tijd'       => '12:00',
		'aantal'     => '2',
		'website'    => 'https://mediamora.nl',
		'bron'       => 'site',
		'hp'         => '',
	),
	'4: waarde per veldtype en label; placeholder-optie overgeslagen; geen waarde voor step, html en een niet-verplichte upload'
);
klopt( mm_formuliertest_waarden( array( veld( 'pw', 'text', 'Postcode en woonplaats' ) ) ), array( 'pw' => '9700 AA Groningen' ), '4: postcode en plaats in één veld' );
klopt( mm_formuliertest_waarden( array( veld( 'a', 'number', 'Aantal' ) ) ), array( 'a' => '1' ), '4: getal zonder minimum: 1' );
klopt( mm_formuliertest_waarden( array( veld( 'cv', 'upload', 'Cv', array( 'required' => 'true' ) ) ) ), 'verplicht uploadveld (Cv)', '4: verplichte upload niet te testen' );
klopt( mm_formuliertest_waarden( array( veld( 'ts', 'turnstile', '' ) ) ), 'captcha (turnstile)', '4: Turnstile niet te testen' );
klopt( mm_formuliertest_waarden( array( veld( 'hc', 'hcaptcha', '' ) ) ), 'captcha (hcaptcha)', '4: hCaptcha niet te testen' );
klopt( mm_formuliertest_waarden( array( veld( 'sig', 'signature', 'Handtekening', array( 'required' => 'true' ) ) ) ), 'verplicht veld van onbekend type signature (Handtekening)', '4: verplicht onbekend veld niet te testen' );
klopt( mm_formuliertest_waarden( array( veld( 'sig', 'signature', 'Handtekening' ) ) ), array(), '4: niet-verplicht onbekend veld overgeslagen' );

/* -------------------------------------------------------------------------
 * 5. Een volledige ronde met fouten
 * ---------------------------------------------------------------------- */

// Een openstaande, gemelde storing: de geslaagde mailroute mag die niet sluiten.
$GLOBALS['opties'][ MM_MONITOR_OPTIE_STORING ] = array( 'sinds' => time() - 3600, 'fouten' => 2, 'gemeld' => true );
$GLOBALS['aangeboden'] = array();
$GLOBALS['nep_gedraaid'] = array();
$GLOBALS['nep_filters']  = array();
$GLOBALS['nep_handler_constructor'] = false;
$post_voor = array( 'iets' => 'van het verzoek zelf' );
$_POST     = $post_voor;

$ronde = mm_formuliertest_ronde( false );
$per   = array();
foreach ( $ronde['formulieren'] as $f ) {
	$per[ $f['formulier'] ] = array( $f['uitkomst'], $f['reden'] );
}

klopt( $per['Nieuwsbrief'], array( 'geslaagd', null ), '5: popup geslaagd' );
klopt( $per['Contact'], array( 'geslaagd', null ), '5: alle velden en acties: geslaagd' );
klopt( $per['Offerte'], array( 'geslaagd', null ), '5: globale widget geslaagd' );
klopt( $per['Terugbellen'], array( 'geslaagd', null ), '5: standaard submit_actions (email) geslaagd' );
klopt( $per['Sollicitatie'], array( 'niet_te_testen', 'verplicht uploadveld (Cv)' ), '5: verplichte upload niet te testen' );
klopt( $per['Met captcha'], array( 'niet_te_testen', 'captcha (recaptcha_v3)' ), '5: captcha niet te testen' );
klopt( $per['Strikt'], array( 'validatiefout', 'veld Telefoon: Alleen internationale nummers.' ), '5: validatiefout met het veld' );
klopt( $per['Fout adres'], array( 'mail_niet_op_te_bouwen', 'email: ongeldig ontvangstadres geen-adres' ), '5: ongeldig adres: mail niet op te bouwen' );
klopt( $per['Exception'], array( 'mail_niet_op_te_bouwen', 'email: Kon de mail niet opbouwen voor ***@klant.nl' ), '5: exception: mail niet op te bouwen, adres gemaskeerd' );
klopt( isset( $per['Alleen webhook'] ), false, '5: formulier zonder mailactie doet niet mee' );

klopt( $ronde['formulieren'][0]['pagina'], 'Nieuwsbrief-popup (popup)', '5: pagina-titel vastgelegd' );

$gedraaid = array_unique( $GLOBALS['nep_gedraaid'] );
sort( $gedraaid );
klopt( $gedraaid, array( 'email', 'email2' ), '5: alleen de mailacties gedraaid, geen webhook, redirect, koppelingen, popup of Inzendingen' );
klopt( array_count_values( $GLOBALS['nep_gedraaid'] )['email2'] ?? 0, 1, '5: email2 alleen bij Contact' );
klopt( $GLOBALS['nep_filters'], array(), '5: submit_actions, actions_before, new_record en after_run niet aangeroepen (Nieuwsbrief alleen bij vinkje doet niets)' );
klopt( $GLOBALS['nep_handler_constructor'], false, '5: Ajax_Handler zonder constructor, dus geen ajax-acties erbij' );
klopt( $_POST, $post_voor, '5: $_POST teruggezet' );
klopt( $GLOBALS['nep_elementor']->db->huidig, array(), '5: Elementor terug op de eigen post' );
klopt( has_filter( 'pre_wp_mail' ) || has_filter( 'wp_mail' ) || has_filter( 'phpmailer_init' ), false, '5: onderscheppen staat na de ronde uit' );
klopt( mm_formuliertest_bezig(), false, '5: test niet meer bezig' );

// Precies één mail de deur uit: de mailroute.
klopt( count( $GLOBALS['aangeboden'] ), 1, '5: één mail via wp_mail, geen per formulier (ook niet Cc, Bcc of Email 2)' );
klopt( $GLOBALS['aangeboden'][0]['to'], array( 'formuliertest@mediamora.nl' ), '5: mailroute naar MM_FORMULIERTEST_ONTVANGER' );
klopt( $GLOBALS['aangeboden'][0]['subject'], '[Formuliertest voorbeeld.nl] mailroute', '5: onderwerp mailroute' );
klopt( in_array( 'X-Mediamora-Monitor: 1', $GLOBALS['aangeboden'][0]['headers'], true ) && in_array( 'X-Mediamora-Status: formuliertest', $GLOBALS['aangeboden'][0]['headers'], true ), true, '5: koppen mailroute' );
klopt( $ronde['mailroute'], array( 'uitkomst' => 'geslaagd', 'melding' => null ), '5: mailroute geslaagd' );
klopt( $GLOBALS['gewist'], 0, '5: pre_wp_mail stopt elke formuliermail vóór phpmailer_init' );
klopt( $ronde['totaal'], 'mislukt', '5: totaal mislukt' );

// De Formuliermonitor merkt er niets van.
klopt( isset( $GLOBALS['opties'][ MM_MONITOR_OPTIE_STORING ] ), true, '5: geslaagde mailroute sluit de storing niet' );
klopt( get_option( MM_MONITOR_OPTIE_LOG, array() ), array(), '5: niets in het log van de monitor' );

// Anti-spam: geen log, geen vangnet, geen rapportmail ingepland.
klopt( file_exists( mediamora_antispam_log_file() ), false, '5: niets in log.txt van de anti-spam' );
klopt( file_exists( mediamora_antispam_nearmiss_file() ), false, '5: niets in near-miss.txt' );
klopt( wp_next_scheduled( 'mediamora_antispam_report' ), false, '5: geen rapportmail ingepland' );

// Eén melding naar MM_MONITOR_ONTVANGER, via mail().
$mails = vangmails();
klopt( count( $mails ), 1, '5: één melding voor de hele ronde' );
$melding = $mails ? file_get_contents( $mails[0] ) : '';
klopt( 1 === preg_match( '/^To: hetty@mediamora\.nl\r?$/m', $melding ), true, '5: melding naar MM_MONITOR_ONTVANGER' );
klopt( false !== strpos( $melding, 'X-Mediamora-Status: formuliertest-mislukt' ), true, '5: kop formuliertest-mislukt' );
klopt( false !== strpos( $melding, 'Subject: [Formuliermonitor] voorbeeld.nl: formuliertest mislukt' ), true, '5: onderwerp melding' );
klopt( false !== strpos( $melding, '- Strikt, formulier "Strikt": validatiefout (veld Telefoon: Alleen internationale nummers.)' ), true, '5: per formulier de uitkomst' );
klopt( false !== strpos( $melding, '- Contact, formulier "Contact": geslaagd' ), true, '5: ook de geslaagde formulieren' );
klopt( false !== strpos( $melding, 'Mailroute: geslaagd' ), true, '5: mailroute in de melding' );
klopt( $ronde['gemeld'], true, '5: gemeld vastgelegd' );
$tekst = substr( $melding, (int) strpos( $melding, "\n\n" ) );
foreach ( array( 'Hetty', '0612345678', '9700 AA', 'Groningen', 'Automatische maandelijkse test van Mediamora' ) as $waarde ) {
	klopt( false !== strpos( $tekst, $waarde ), false, '5: geen ingevulde waarde in de melding: ' . $waarde );
}

/* -------------------------------------------------------------------------
 * 6. Geweigerd door de anti-spam
 * ---------------------------------------------------------------------- */

$kandidaat = array(
	'sleutel'    => '16:adres',
	'post_id'    => 10,
	'form_id'    => 'f1',
	'queried_id' => 10,
	'pagina'     => 'Contact',
	'url'        => 'https://www.voorbeeld.nl/contact/',
);

mm_formuliertest_bezig( true );
$uit = mm_formuliertest_test( $kandidaat );
klopt( $uit['uitkomst'], 'geslaagd', '6: met referrer niet geweigerd door de referrercheck' );

$uit = mm_formuliertest_test( array( 'url' => '' ) + $kandidaat );
klopt( array( $uit['uitkomst'], $uit['reden'] ), array( 'geweigerd', 'verstuurd zonder JavaScript (geen referrer) (veld referrer)' ), '6: zonder referrer geweigerd, met de reden' );

// Op mediamora.nl zelf noemt een als tekstveld gebouwd e-mailveld de eigen domeinnaam.
$GLOBALS['home'] = 'https://mediamora.nl';
$d->data[19]     = array( formulier( 'eigen', 'Eigen domein', array( veld( 'name', 'text', 'Naam' ), veld( 'mail', 'text', 'Je e-mail' ) ) ) );
$uit             = mm_formuliertest_test( array( 'post_id' => 19, 'form_id' => 'eigen' ) + $kandidaat );
klopt( array( $uit['uitkomst'], $uit['reden'] ), array( 'geweigerd', 'noemt eigen domeinnaam (veld mail)' ), '6: geweigerd op de inhoud, met reden en veld' );
$GLOBALS['home'] = 'https://www.voorbeeld.nl';
mm_formuliertest_bezig( false );

klopt( file_exists( mediamora_antispam_log_file() ), false, '6: ook na een weigering niets in log.txt' );

// Een captcha van een plugin die niet als veld in het formulier staat.
$uit = mm_formuliertest_validatiefout( array( 'cf-turnstile' => 'Please verify that you are human.' ), $eenvoudig );
klopt( $uit['uitkomst'], 'niet_te_testen', '6: captcha buiten het formulier: niet te testen' );
$uit = mm_formuliertest_validatiefout( array( 'name' => 'Please verify that you are human.' ), $eenvoudig );
klopt( $uit['uitkomst'], 'validatiefout', '6: dezelfde tekst bij een eigen veld blijft een validatiefout' );

/* -------------------------------------------------------------------------
 * 7. Onderscheppen als een plugin pre_wp_mail overslaat
 * ---------------------------------------------------------------------- */

$GLOBALS['negeer_pre_wp_mail'] = true;
$GLOBALS['aangeboden']         = array();
$GLOBALS['na_filters']         = array();
$GLOBALS['gewist']             = 0;
mm_formuliertest_bezig( true );
$uit = mm_formuliertest_test( $kandidaat );
mm_formuliertest_bezig( false );
$GLOBALS['negeer_pre_wp_mail'] = false;
klopt( $GLOBALS['aangeboden'], array(), '7: zonder pre_wp_mail toch geen ontvangers, ook niet Cc of Bcc' );
klopt( count( $GLOBALS['na_filters'] ), 3, '7: email, Bcc en email2 kwamen langs wp_mail' );
klopt( array_unique( array_map( 'count', $GLOBALS['na_filters'] ) ), array( 0 ), '7: het filter wp_mail haalt alle ontvangers weg, ook Cc en Bcc in de koppen' );
klopt( $uit['uitkomst'], 'geslaagd', '7: mail opgebouwd, dus geslaagd' );

// Elke laag los.
mm_formuliertest_mail( null );
$atts = mm_formuliertest_wp_mail(
	array(
		'to'      => array( 'info@voorbeeld.nl' ),
		'subject' => 'Onderwerp',
		'message' => 'Inhoud',
		'headers' => "From: X <x@voorbeeld.nl>\r\nCc: cc@voorbeeld.nl\r\nBCC: bcc@voorbeeld.nl\r\n",
	)
);
klopt( array( $atts['to'], $atts['headers'] ), array( array(), array( 'From: X <x@voorbeeld.nl>', '' ) ), '7: filter wp_mail: geen ontvangers, geen Cc of Bcc' );
klopt( mm_formuliertest_mail( null ), array( array( 'to' => array( 'info@voorbeeld.nl' ), 'subject' => 'Onderwerp', 'message' => 'Inhoud' ) ), '7: filter wp_mail legt de opgebouwde mail vast' );
klopt( mm_formuliertest_pre_wp_mail( null ), true, '7: pre_wp_mail stopt wp_mail() met true' );
klopt( mm_formuliertest_mails_beoordelen( array() ), 'wp_mail niet aangeroepen', '7: geen mail: mislukt' );
klopt( mm_formuliertest_mails_beoordelen( array( array( 'to' => 'Info <info@voorbeeld.nl>, ander@voorbeeld.nl', 'subject' => 'X', 'message' => 'Y' ) ) ), '', '7: adres met naam en een lijst met komma\'s mag' );
klopt( mm_formuliertest_mails_beoordelen( array( array( 'to' => 'info@voorbeeld.nl', 'subject' => ' ', 'message' => 'Y' ) ) ), 'geen onderwerp', '7: zonder onderwerp' );
klopt( mm_formuliertest_mails_beoordelen( array( array( 'to' => 'info@voorbeeld.nl', 'subject' => 'X', 'message' => '' ) ) ), 'geen inhoud', '7: zonder inhoud' );
klopt( mm_formuliertest_mails_beoordelen( array( array( 'to' => array(), 'subject' => 'X', 'message' => 'Y' ) ) ), 'geen ontvanger', '7: zonder ontvanger' );

$mailer = new Nep_Mailer( array( 'iemand@voorbeeld.nl' ) );
mm_formuliertest_phpmailer( $mailer );
klopt( $mailer->ontvangers, array(), '7: phpmailer_init haalt de ontvangers weg' );

/* -------------------------------------------------------------------------
 * 8. Geen melding als alles goed gaat; mislukte mailroute
 * ---------------------------------------------------------------------- */

$GLOBALS['nep_paginas']   = array( 10, 11 );
$GLOBALS['nep_templates'] = array();
$voor  = count( vangmails() );
$ronde = mm_formuliertest_ronde( false );
klopt( $ronde['totaal'], 'in_orde', '8: alles geslaagd: in orde' );
klopt( count( vangmails() ), $voor, '8: geen melding als alles goed gaat' );
klopt( $ronde['gemeld'], null, '8: niet gemeld' );

$ronde = mm_formuliertest_ronde( false );
$GLOBALS['nep_paginas'] = array( 12, 13 );
$ronde = mm_formuliertest_ronde( false );
klopt( $ronde['totaal'], 'in_orde', '8: alleen niet te testen en een geslaagde mailroute: in orde' );
klopt( count( vangmails() ), $voor, '8: niet te testen geeft geen melding' );

// Mislukte mailroute: melding, en geen storing in de Formuliermonitor.
unset( $GLOBALS['opties'][ MM_MONITOR_OPTIE_STORING ] );
$GLOBALS['nep_paginas'] = array();
$GLOBALS['mail_faalt']  = true;
$ronde                  = mm_formuliertest_ronde( false );
$GLOBALS['mail_faalt']  = false;
klopt( $ronde['mailroute'], array( 'uitkomst' => 'mislukt', 'melding' => 'SMTP Error: Could not authenticate ***@host.nl' ), '8: mailroute mislukt, melding gemaskeerd' );
klopt( $ronde['totaal'], 'mislukt', '8: mislukte mailroute: totaal mislukt' );
klopt( count( vangmails() ), $voor + 1, '8: melding bij een mislukte mailroute' );
klopt( get_option( MM_MONITOR_OPTIE_STORING, null ), null, '8: geen storing in de Formuliermonitor' );
klopt( get_option( MM_MONITOR_OPTIE_TELLER, null ), null, '8: dagteller van de monitor ongemoeid' );

// Een formulier dat bij het laden een fout geeft: de rest van de ronde gaat door.
$d->data[20] = array( formulier( 'kapot', 'Kapot', $eenvoudig, array( 'nep_laadfout' => true ) ) );
$GLOBALS['titels'][20] = 'Kapot';
$GLOBALS['nep_paginas'] = array( 20, 10 );
$GLOBALS['nep_templates'] = array();
$extra = mm_formuliertest_ronde( false );
klopt( array( $extra['formulieren'][0]['uitkomst'], $extra['formulieren'][0]['reden'] ), array( 'mail_niet_op_te_bouwen', 'fout bij het verwerken: Widget kan niet laden' ), '8: fout bij het laden telt als mislukt' );
klopt( $extra['formulieren'][1]['uitkomst'], 'geslaagd', '8: de ronde gaat door na een fout' );
klopt( mm_formuliertest_bezig(), false, '8: na een fout niet meer bezig' );
array_map( 'unlink', vangmails() );

/* -------------------------------------------------------------------------
 * 9. De sync, schema 2
 * ---------------------------------------------------------------------- */

$GLOBALS['nep_paginas']   = array( 10, 11, 12, 13, 15, 16, 17 );
$GLOBALS['nep_templates'] = array( (object) array( 'ID' => 70, 'post_title' => 'Nieuwsbrief-popup', 'soort' => 'popup' ) );
mm_formuliertest_ronde( false );

do_action( 'plugins_loaded' );
$status = apply_filters( 'mainwp_site_sync_others_data', array(), array( 'mediamora_toolkit' => 1 ) )['mediamora_toolkit'];
$json   = json_encode( $status );

klopt( $status['schema'], 2, '9: schema 2' );
klopt( array_keys( $status ), array( 'schema', 'toolkit_versie', 'onderhoudsklant', 'formuliermonitor', 'anti_spam', 'testinzending' ), '9: dezelfde sleutels als schema 1' );
klopt( array_keys( $status['formuliermonitor'] ), array( 'aan', 'storing', 'laatste_fout', 'laatste_geslaagde_mail', 'log' ), '9: formuliermonitor gelijk aan schema 1' );
klopt( array_keys( $status['testinzending'] ), array( 'laatste_ronde', 'handmatig', 'volgende_ronde', 'totaal', 'mailroute', 'formulieren' ), '9: sleutels testinzending' );
klopt( array_keys( $status['testinzending']['formulieren'][0] ), array( 'pagina', 'formulier', 'uitkomst', 'reden' ), '9: sleutels per formulier' );
klopt( array_keys( $status['testinzending']['mailroute'] ), array( 'uitkomst', 'melding' ), '9: sleutels mailroute' );
klopt( $status['testinzending']['totaal'], 'mislukt', '9: totaal' );
klopt( 1 === preg_match( '/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d[+-]\d\d:\d\d$/', (string) $status['testinzending']['laatste_ronde'] ), true, '9: laatste ronde als ISO 8601' );
klopt( $status['testinzending']['volgende_ronde'], wp_date( DATE_ATOM, wp_next_scheduled( MM_FORMULIERTEST_HOOK ) ), '9: volgende ronde' );
klopt( array_keys( $status['anti_spam'] ), array( 'periode', 'totaal', 'menselijk', 'postcode', 'menselijke' ), '9: sleutels anti_spam' );
foreach ( array( 'Hetty', 'hetty@mediamora.nl', '0612345678', '9700 AA', 'Groningen', 'Automatische maandelijkse test', 'Websites', 'ochtend' ) as $waarde ) {
	klopt( false !== stripos( $json, $waarde ), false, '9: geen ingevulde waarde in de sync: ' . $waarde );
}
klopt( false !== strpos( serialize( get_option( MM_FORMULIERTEST_OPTIE ) ), 'Hetty' ), false, '9: ook niet in de bewaarde uitkomst' );

klaar();
