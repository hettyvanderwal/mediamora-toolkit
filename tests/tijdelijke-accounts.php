<?php
/**
 * Mediamora Toolkit, testgevallen voor de module Tijdelijke accounts.
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/tijdelijke-accounts.php
 * Geeft exitcode 1 als er een geval misgaat.
 *
 * De WordPress-functies hieronder zijn een kleine nabootsing met gebruikers
 * en meta in het geheugen. Zo lopen het opruimen en de mail echt van begin
 * tot eind, inclusief een get_users die net als WordPress een lege waarde als
 * 0 vergelijkt.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['t'] = array();

class WP_User {
	public $ID;
	public $user_login;
	public $user_email;
	public $display_name;
	public $roles;
	public function __construct( $rij ) {
		foreach ( $rij as $k => $v ) {
			$this->$k = $v;
		}
	}
}

class WP_Error {
	public $fouten = array();
	public function __construct( $code = '', $tekst = '' ) {
		if ( $code ) {
			$this->add( $code, $tekst );
		}
	}
	public function add( $code, $tekst ) {
		$this->fouten[ $code ] = $tekst;
	}
	public function get_error_message() {
		return (string) reset( $this->fouten );
	}
}

class WP_Session_Tokens {
	private $id;
	public static function get_instance( $id ) {
		$s     = new self();
		$s->id = $id;
		return $s;
	}
	public function destroy_all() {
		// Sessies moeten weg zijn terwijl het account nog bestaat.
		$GLOBALS['t']['sessies'][] = array( $this->id, isset( $GLOBALS['t']['users'][ $this->id ] ) );
	}
}

function add_action( $hook, $functie, $prio = 10, $args = 1 ) {
	$GLOBALS['t']['hooks'][ $hook ][] = $functie;
}
function add_filter( $hook, $functie, $prio = 10, $args = 1 ) {
	$GLOBALS['t']['hooks'][ $hook ][] = $functie;
	$GLOBALS['t']['prio'][ $hook ]    = $prio;
}
function apply_filters( $hook, $waarde ) {
	return $waarde;
}
function get_user_meta( $id, $sleutel, $enkel = false ) {
	return isset( $GLOBALS['t']['meta'][ $id ][ $sleutel ] ) ? $GLOBALS['t']['meta'][ $id ][ $sleutel ] : '';
}
function update_user_meta( $id, $sleutel, $waarde ) {
	$GLOBALS['t']['meta'][ $id ][ $sleutel ] = $waarde;
}
function delete_user_meta( $id, $sleutel ) {
	unset( $GLOBALS['t']['meta'][ $id ][ $sleutel ] );
}
function get_user_by( $veld, $id ) {
	return isset( $GLOBALS['t']['users'][ (int) $id ] ) ? new WP_User( $GLOBALS['t']['users'][ (int) $id ] ) : false;
}
function get_users( $args = array() ) {
	$uit = array();
	foreach ( $GLOBALS['t']['users'] as $id => $rij ) {
		if ( isset( $args['meta_key'] ) ) {
			if ( ! isset( $GLOBALS['t']['meta'][ $id ][ $args['meta_key'] ] ) ) {
				continue;
			}
			$waarde = (int) $GLOBALS['t']['meta'][ $id ][ $args['meta_key'] ];
			$grens  = (int) $args['meta_value'];
			$goed   = array(
				'<'  => $waarde < $grens,
				'<=' => $waarde <= $grens,
				'>'  => $waarde > $grens,
			);
			if ( ! $goed[ $args['meta_compare'] ] ) {
				continue;
			}
		}
		if ( isset( $args['capability'] ) && ! in_array( 'administrator', $rij['roles'], true ) ) {
			continue;
		}
		if ( isset( $args['search'] ) && false === strpos( strtolower( $rij['user_email'] ), trim( $args['search'], '*' ) ) ) {
			continue;
		}
		$uit[] = new WP_User( $rij );
	}
	return $uit;
}
function wp_delete_user( $id, $erfgenaam ) {
	if ( ! empty( $GLOBALS['t']['verwijderen_mislukt'] ) ) {
		return false;
	}
	unset( $GLOBALS['t']['users'][ $id ], $GLOBALS['t']['meta'][ $id ] );
	$GLOBALS['t']['verwijderd'][ $id ] = $erfgenaam;
	return true;
}
function wp_mail( $aan, $onderwerp, $tekst ) {
	$GLOBALS['t']['mails'][] = array( 'aan' => (array) $aan, 'onderwerp' => $onderwerp, 'tekst' => $tekst );
	return true;
}
function get_option( $naam, $standaard = false ) {
	return array_key_exists( $naam, $GLOBALS['t']['opties'] ) ? $GLOBALS['t']['opties'][ $naam ] : $standaard;
}
function home_url() {
	return 'https://www.voorbeeld.nl';
}
function wp_parse_url( $url, $deel ) {
	return parse_url( $url, $deel );
}
function wp_timezone() {
	return new DateTimeZone( 'Europe/Amsterdam' );
}
function wp_date( $formaat, $moment ) {
	return ( new DateTimeImmutable( '@' . $moment ) )->setTimezone( wp_timezone() )->format( $formaat );
}
function current_user_can( $recht, $id = 0 ) {
	return in_array( $recht, $GLOBALS['t']['rechten'], true );
}
function mm_toolkit_mediamora_gebruiker() {
	return $GLOBALS['t']['mediamora'];
}
function is_multisite() {
	return ! empty( $GLOBALS['t']['multisite'] );
}
function wp_verify_nonce( $nonce, $actie ) {
	return $nonce === 'nonce-' . $actie;
}
function sanitize_key( $w ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $w ) );
}
function sanitize_text_field( $w ) {
	return trim( $w );
}
function wp_unslash( $w ) {
	return $w;
}
function absint( $w ) {
	return abs( (int) $w );
}
function esc_attr( $w ) {
	return htmlspecialchars( (string) $w, ENT_QUOTES );
}
function esc_html( $w ) {
	return htmlspecialchars( (string) $w, ENT_QUOTES );
}
function esc_url( $w ) {
	return (string) $w;
}
function wp_nonce_field( $actie, $naam, $referer = true ) {
	echo '<input type="hidden" name="' . $naam . '" value="nonce-' . $actie . '">';
}
function wp_dropdown_users( $args ) {
	$GLOBALS['t']['dropdown'] = $args;
	return '<select></select>';
}
function wp_roles() {
	return new class() {
		public function get_names() {
			return array( 'administrator' => 'Administrator', 'editor' => 'Editor' );
		}
	};
}
function translate_user_role( $naam ) {
	return $naam;
}
function get_edit_user_link( $id ) {
	return 'user-edit.php?user_id=' . $id;
}
function wp_next_scheduled( $hook ) {
	return isset( $GLOBALS['t']['cron'][ $hook ] ) ? $GLOBALS['t']['cron'][ $hook ] : false;
}
function wp_schedule_event( $moment, $interval, $hook ) {
	$GLOBALS['t']['cron'][ $hook ] = $moment;
}

const MM_TOOLKIT_TIJDELIJK_MAIL_OPTIE = 'mm_tijdelijke_accounts_mail';

// Het echte bestand: alleen functies, dus zonder WordPress te laden.
require dirname( __DIR__ ) . '/includes/modules.php';
require dirname( __DIR__ ) . '/modules/tijdelijke-accounts.php';


/* -------------------------------------------------------------------------
 * Beginstand per geval
 *
 * 1 Hetty (Mediamora), 2 klant, 3 marketeer (tijdelijk), 4 redacteur.
 * ---------------------------------------------------------------------- */

function beginstand() {
	$gebruiker = function ( $id, $naam, $adres, $rol ) {
		return array(
			'ID'           => $id,
			'user_login'   => strtolower( $naam ),
			'user_email'   => $adres,
			'display_name' => $naam,
			'roles'        => array( $rol ),
		);
	};

	$GLOBALS['t'] = array(
		'users'     => array(
			1 => $gebruiker( 1, 'Hetty', 'Hetty@Mediamora.NL', 'administrator' ),
			2 => $gebruiker( 2, 'Klant', 'klant@voorbeeld.nl', 'administrator' ),
			3 => $gebruiker( 3, 'Marketeer', 'pixel@bureau.nl', 'administrator' ),
			4 => $gebruiker( 4, 'Redacteur', 'tekst@voorbeeld.nl', 'editor' ),
		),
		'meta'      => array(),
		'opties'    => array(),
		'mails'     => array(),
		'sessies'   => array(),
		'verwijderd' => array(),
		'hooks'     => $GLOBALS['t']['hooks'] ?? array(),
		'prio'      => $GLOBALS['t']['prio'] ?? array(),
		'mediamora' => true,
		'rechten'   => array( 'manage_options', 'edit_user', 'create_users' ),
	);
	$_POST = array();
}

function verlopen_op( $id, $erfgenaam = 0, $moment = null ) {
	$GLOBALS['t']['meta'][ $id ]['mm_verloopt_op'] = null === $moment ? time() - 60 : $moment;
	if ( $erfgenaam ) {
		$GLOBALS['t']['meta'][ $id ]['mm_overdragen_aan'] = $erfgenaam;
	}
}

function formulier( $id, $velden ) {
	$_POST = array_merge( array( 'mm_tijdelijk_nonce' => 'nonce-mm_tijdelijk_' . $id ), $velden );
}

$gevallen = array();

function geval( $naam, $functie ) {
	$GLOBALS['gevallen'][ $naam ] = $functie;
}

function klopt( $uitkomst, $verwacht, $wat = '' ) {
	if ( $uitkomst !== $verwacht ) {
		throw new Exception( sprintf( '%s: %s, verwacht %s', $wat, var_export( $uitkomst, true ), var_export( $verwacht, true ) ) );
	}
}


/* -------------------------------------------------------------------------
 * Blokkeren
 * ---------------------------------------------------------------------- */

geval( 'een datum in de toekomst is niet verlopen', function () {
	verlopen_op( 3, 0, time() + 3600 );
	klopt( mm_tijdelijk_verlopen( 3 ), false );
} );

geval( 'een datum in het verleden is verlopen', function () {
	verlopen_op( 3 );
	klopt( mm_tijdelijk_verlopen( 3 ), true );
} );

geval( 'geen, lege of 0-waarde is niet verlopen', function () {
	klopt( mm_tijdelijk_verlopen( 3 ), false, 'geen' );
	verlopen_op( 3, 0, '' );
	klopt( mm_tijdelijk_verlopen( 3 ), false, 'leeg' );
	verlopen_op( 3, 0, 0 );
	klopt( mm_tijdelijk_verlopen( 3 ), false, 'nul' );
} );

geval( 'determine_current_user geeft 0 voor een verlopen account', function () {
	verlopen_op( 3 );
	klopt( mm_tijdelijk_huidige_gebruiker( 3 ), 0, 'verlopen' );
	klopt( mm_tijdelijk_huidige_gebruiker( 2 ), 2, 'gewoon account' );
	klopt( mm_tijdelijk_huidige_gebruiker( false ), false, 'niet ingelogd' );
} );

geval( 'determine_current_user draait na cookie en applicatiewachtwoord', function () {
	klopt( $GLOBALS['t']['prio']['determine_current_user'], 99 );
} );

geval( 'wp_authenticate_user weigert met de afgesproken melding', function () {
	verlopen_op( 3 );
	$fout = mm_tijdelijk_weiger_inloggen( get_user_by( 'id', 3 ) );
	klopt( $fout instanceof WP_Error, true, 'soort' );
	klopt( $fout->get_error_message(), 'Dit account is verlopen.', 'melding' );
	klopt( in_array( 'mm_tijdelijk_weiger_inloggen', $GLOBALS['t']['hooks']['wp_authenticate_user'], true ), true, 'hook' );
} );

geval( 'authenticate weigert ook, voor XML-RPC met een applicatiewachtwoord', function () {
	klopt( in_array( 'mm_tijdelijk_weiger_inloggen', $GLOBALS['t']['hooks']['authenticate'], true ), true );
} );

geval( 'een fout of een gewoon account gaat ongemoeid door het inlogfilter', function () {
	$fout = new WP_Error( 'x', 'y' );
	klopt( mm_tijdelijk_weiger_inloggen( $fout ), $fout, 'fout' );
	klopt( mm_tijdelijk_weiger_inloggen( null ), null, 'null' );
	klopt( mm_tijdelijk_weiger_inloggen( get_user_by( 'id', 2 ) ) instanceof WP_User, true, 'gewoon account' );
} );


/* -------------------------------------------------------------------------
 * Opruimen
 * ---------------------------------------------------------------------- */

geval( 'cron plant in op de hook van de losse versie', function () {
	mm_tijdelijk_inplannen();
	klopt( wp_next_scheduled( 'mm_tijdelijke_accounts_opruimen' ) > time(), true, 'ingepland' );
	klopt( in_array( 'mm_tijdelijk_opruimen', $GLOBALS['t']['hooks']['mm_tijdelijke_accounts_opruimen'], true ), true, 'hook' );
} );

geval( 'een event van de losse versie blijft staan', function () {
	$GLOBALS['t']['cron']['mm_tijdelijke_accounts_opruimen'] = 12345;
	mm_tijdelijk_inplannen();
	klopt( wp_next_scheduled( 'mm_tijdelijke_accounts_opruimen' ), 12345 );
} );

geval( 'verwijdert een verlopen account en draagt over aan de erfgenaam', function () {
	verlopen_op( 3, 2 );
	mm_tijdelijk_opruimen();
	klopt( $GLOBALS['t']['verwijderd'], array( 3 => 2 ) );
} );

geval( 'sessies gaan weg voordat het account verdwijnt', function () {
	verlopen_op( 3, 2 );
	mm_tijdelijk_opruimen();
	klopt( $GLOBALS['t']['sessies'], array( array( 3, true ) ) );
} );

geval( 'laat een account staan zonder erfgenaam, met onbekende erfgenaam of met zichzelf', function () {
	verlopen_op( 3 );
	mm_tijdelijk_opruimen();
	verlopen_op( 3, 99 );
	mm_tijdelijk_opruimen();
	verlopen_op( 3, 3 );
	mm_tijdelijk_opruimen();
	klopt( $GLOBALS['t']['verwijderd'], array(), 'verwijderd' );
	klopt( $GLOBALS['t']['mails'], array(), 'mails' );
	klopt( mm_tijdelijk_verlopen( 3 ), true, 'blijft geblokkeerd' );
} );

geval( 'een lege verloopdatum verwijdert niets, ook al telt die in de query als verleden', function () {
	verlopen_op( 3, 2, '' );
	mm_tijdelijk_opruimen();
	klopt( $GLOBALS['t']['verwijderd'], array() );
} );

geval( 'een toekomstige datum verwijdert niets', function () {
	verlopen_op( 3, 2, time() + 3600 );
	mm_tijdelijk_opruimen();
	klopt( $GLOBALS['t']['verwijderd'], array() );
} );

geval( 'twee accounts die naar elkaar wijzen geven geen content aan een verdwenen account', function () {
	verlopen_op( 3, 4 );
	verlopen_op( 4, 3 );
	mm_tijdelijk_opruimen();
	klopt( $GLOBALS['t']['verwijderd'], array( 3 => 4 ) );
} );


/* -------------------------------------------------------------------------
 * Mail
 * ---------------------------------------------------------------------- */

geval( 'de mail gaat naar de Mediamora-gebruiker, niet naar de klant', function () {
	verlopen_op( 3, 2 );
	mm_tijdelijk_opruimen();
	klopt( count( $GLOBALS['t']['mails'] ), 1, 'aantal' );
	klopt( $GLOBALS['t']['mails'][0]['aan'], array( 'Hetty@Mediamora.NL' ), 'ontvanger' );
} );

geval( 'een adres op een subdomein is geen Mediamora-gebruiker', function () {
	$GLOBALS['t']['users'][1]['user_email'] = 'hetty@sub.mediamora.nl';
	klopt( mm_tijdelijk_mail_ontvangers(), array( 'hetty@mediamora.nl' ) );
} );

geval( 'zonder Mediamora-gebruiker valt de mail terug op het vaste adres', function () {
	unset( $GLOBALS['t']['users'][1] );
	verlopen_op( 3, 2 );
	mm_tijdelijk_opruimen();
	klopt( $GLOBALS['t']['mails'][0]['aan'], array( 'hetty@mediamora.nl' ) );
} );

geval( 'de mail noemt account en erfgenaam', function () {
	verlopen_op( 3, 2 );
	mm_tijdelijk_opruimen();
	$tekst = $GLOBALS['t']['mails'][0]['tekst'];
	klopt( false !== strpos( $tekst, 'Marketeer (marketeer, pixel@bureau.nl)' ), true, 'account' );
	klopt( false !== strpos( $tekst, 'Content overgedragen aan: Klant (klant)' ), true, 'erfgenaam' );
} );

geval( 'de lijst met beheerders is van na het verwijderen', function () {
	verlopen_op( 3, 2 );
	$GLOBALS['t']['users'][5] = array(
		'ID'           => 5,
		'user_login'   => 'extra',
		'user_email'   => 'extra@bureau.nl',
		'display_name' => 'Extra',
		'roles'        => array( 'administrator' ),
	);
	mm_tijdelijk_opruimen();
	$lijst = substr( $GLOBALS['t']['mails'][0]['tekst'], strpos( $GLOBALS['t']['mails'][0]['tekst'], 'Beheerders' ) );
	klopt( false !== strpos( $lijst, 'Extra (extra, extra@bureau.nl)' ), true, 'extra beheerder' );
	klopt( false !== strpos( $lijst, 'Klant' ), true, 'klant' );
	klopt( strpos( $lijst, 'Marketeer' ), false, 'verwijderd account' );
	klopt( strpos( $lijst, 'Redacteur' ), false, 'geen beheerder' );
} );

geval( 'twee accounts in een ronde geven een mail met allebei', function () {
	verlopen_op( 3, 2 );
	verlopen_op( 4, 2 );
	mm_tijdelijk_opruimen();
	klopt( count( $GLOBALS['t']['mails'] ), 1, 'aantal' );
	klopt( false !== strpos( $GLOBALS['t']['mails'][0]['tekst'], 'Redacteur' ), true, 'tweede account' );
} );

geval( 'de mail is uit te zetten en staat standaard aan', function () {
	klopt( mm_tijdelijk_mail_aan(), true, 'standaard' );
	$GLOBALS['t']['opties'][ MM_TOOLKIT_TIJDELIJK_MAIL_OPTIE ] = '0';
	klopt( mm_tijdelijk_mail_aan(), false, 'uit' );
	verlopen_op( 3, 2 );
	mm_tijdelijk_opruimen();
	klopt( $GLOBALS['t']['mails'], array(), 'geen mail' );
	klopt( $GLOBALS['t']['verwijderd'], array( 3 => 2 ), 'wel verwijderd' );
} );

geval( 'geen mail als verwijderen mislukt', function () {
	verlopen_op( 3, 2 );
	$GLOBALS['t']['verwijderen_mislukt'] = true;
	mm_tijdelijk_opruimen();
	klopt( $GLOBALS['t']['mails'], array() );
} );


/* -------------------------------------------------------------------------
 * Datum
 * ---------------------------------------------------------------------- */

geval( 'een datum verloopt om 23:59:59 in de winter', function () {
	klopt( mm_tijdelijk_datum_naar_moment( '2026-12-15' ), strtotime( '2026-12-15T23:59:59+01:00' ) );
} );

geval( 'een datum verloopt om 23:59:59 in de zomer', function () {
	klopt( mm_tijdelijk_datum_naar_moment( '2026-07-15' ), strtotime( '2026-07-15T23:59:59+02:00' ) );
} );

geval( 'een ongeldige datum geeft 0', function () {
	klopt( mm_tijdelijk_datum_naar_moment( '2026-02-31' ), 0, '31 februari' );
	klopt( mm_tijdelijk_datum_naar_moment( '15-12-2026' ), 0, 'andere volgorde' );
	klopt( mm_tijdelijk_datum_naar_moment( 'morgen' ), 0, 'tekst' );
} );


/* -------------------------------------------------------------------------
 * Opslaan
 * ---------------------------------------------------------------------- */

geval( 'slaat datum en erfgenaam op', function () {
	formulier( 3, array( 'mm_verloopt_op' => '2026-12-15', 'mm_overdragen_aan' => '2' ) );
	mm_tijdelijk_opslaan( 3 );
	klopt( get_user_meta( 3, 'mm_verloopt_op', true ), strtotime( '2026-12-15T23:59:59+01:00' ), 'datum' );
	klopt( get_user_meta( 3, 'mm_overdragen_aan', true ), 2, 'erfgenaam' );
} );

geval( 'een lege datum haalt de verloopdatum weg', function () {
	verlopen_op( 3, 2 );
	formulier( 3, array( 'mm_verloopt_op' => '', 'mm_overdragen_aan' => '0' ) );
	mm_tijdelijk_opslaan( 3 );
	klopt( isset( $GLOBALS['t']['meta'][3]['mm_verloopt_op'] ), false, 'datum' );
	klopt( isset( $GLOBALS['t']['meta'][3]['mm_overdragen_aan'] ), false, 'erfgenaam' );
} );

geval( 'zonder nonce blijft alles staan', function () {
	verlopen_op( 3, 2, 777 );
	$_POST = array( 'mm_verloopt_op' => '', 'mm_overdragen_aan' => '0' );
	mm_tijdelijk_opslaan( 3 );
	klopt( get_user_meta( 3, 'mm_verloopt_op', true ), 777 );
} );

geval( 'een nonce voor een ander account telt niet', function () {
	formulier( 4, array( 'mm_verloopt_op' => '2026-12-15', 'mm_overdragen_aan' => '2' ) );
	mm_tijdelijk_opslaan( 3 );
	klopt( get_user_meta( 3, 'mm_verloopt_op', true ), '' );
} );

geval( 'een beheerder van de klant kan niets wijzigen', function () {
	verlopen_op( 3, 2, 777 );
	$GLOBALS['t']['mediamora'] = false;
	formulier( 3, array( 'mm_verloopt_op' => '', 'mm_overdragen_aan' => '0' ) );
	mm_tijdelijk_opslaan( 3 );
	klopt( get_user_meta( 3, 'mm_verloopt_op', true ), 777 );
} );

geval( 'zonder recht op dit account gaat er niets door', function () {
	$GLOBALS['t']['rechten'] = array( 'manage_options' );
	formulier( 3, array( 'mm_verloopt_op' => '2026-12-15', 'mm_overdragen_aan' => '2' ) );
	mm_tijdelijk_opslaan( 3 );
	klopt( get_user_meta( 3, 'mm_verloopt_op', true ), '' );
} );

geval( 'het eigen account of een onbekend account wordt geen erfgenaam', function () {
	formulier( 3, array( 'mm_verloopt_op' => '2026-12-15', 'mm_overdragen_aan' => '3' ) );
	mm_tijdelijk_opslaan( 3 );
	klopt( isset( $GLOBALS['t']['meta'][3]['mm_overdragen_aan'] ), false, 'zichzelf' );
	formulier( 3, array( 'mm_verloopt_op' => '2026-12-15', 'mm_overdragen_aan' => '99' ) );
	mm_tijdelijk_opslaan( 3 );
	klopt( isset( $GLOBALS['t']['meta'][3]['mm_overdragen_aan'] ), false, 'onbekend' );
} );

geval( 'een nieuwe gebruiker krijgt de velden van het formulier', function () {
	formulier( 0, array( 'mm_verloopt_op' => '2026-12-15', 'mm_overdragen_aan' => '2' ) );
	mm_tijdelijk_nieuwe_gebruiker( 3 );
	klopt( (bool) get_user_meta( 3, 'mm_verloopt_op', true ), true );
} );

geval( 'een Mediamora-adres houdt nooit een verloopdatum', function () {
	verlopen_op( 1, 2 );
	formulier( 1, array( 'mm_verloopt_op' => '2026-12-15', 'mm_overdragen_aan' => '2' ) );
	mm_tijdelijk_opslaan( 1 );
	klopt( isset( $GLOBALS['t']['meta'][1]['mm_verloopt_op'] ), false, 'datum' );
	klopt( isset( $GLOBALS['t']['meta'][1]['mm_overdragen_aan'] ), false, 'erfgenaam' );
} );


/* -------------------------------------------------------------------------
 * Controle voor het opslaan
 * ---------------------------------------------------------------------- */

function controle( $id, $adres, $velden ) {
	formulier( $id, $velden );
	$fouten = new WP_Error();
	$user   = (object) array( 'user_email' => $adres );
	if ( $id ) {
		$user->ID = $id;
	}
	mm_tijdelijk_controleren( $fouten, (bool) $id, $user );
	return $fouten->fouten;
}

geval( 'geen verloopdatum op een Mediamora-adres, ook niet als het adres net wijzigt', function () {
	klopt( array_keys( controle( 3, 'nieuw@MEDIAMORA.nl', array( 'mm_verloopt_op' => '2026-12-15', 'mm_overdragen_aan' => '2' ) ) ), array( 'mm_tijdelijk_mediamora' ), 'bestaand account' );
	klopt( array_keys( controle( 0, 'nieuw@mediamora.nl', array( 'mm_verloopt_op' => '2026-12-15', 'mm_overdragen_aan' => '2' ) ) ), array( 'mm_tijdelijk_mediamora' ), 'nieuw account' );
} );

geval( 'een Mediamora-adres zonder datum mag gewoon', function () {
	klopt( controle( 3, 'nieuw@mediamora.nl', array( 'mm_verloopt_op' => '', 'mm_overdragen_aan' => '0' ) ), array() );
} );

geval( 'een ongeldige datum wordt geweigerd', function () {
	klopt( array_keys( controle( 3, 'pixel@bureau.nl', array( 'mm_verloopt_op' => '2026-02-31', 'mm_overdragen_aan' => '2' ) ) ), array( 'mm_tijdelijk_datum' ) );
} );

geval( 'het account zelf als erfgenaam wordt geweigerd', function () {
	klopt( array_keys( controle( 3, 'pixel@bureau.nl', array( 'mm_verloopt_op' => '2026-12-15', 'mm_overdragen_aan' => '3' ) ) ), array( 'mm_tijdelijk_erfgenaam' ) );
} );

geval( 'een beheerder van de klant krijgt geen foutmelding van deze module', function () {
	$GLOBALS['t']['mediamora'] = false;
	klopt( controle( 3, 'nieuw@mediamora.nl', array( 'mm_verloopt_op' => 'onzin', 'mm_overdragen_aan' => '3' ) ), array() );
} );


/* -------------------------------------------------------------------------
 * Velden en overzicht
 * ---------------------------------------------------------------------- */

function uitvoer( $functie, ...$args ) {
	ob_start();
	$functie( ...$args );
	return ob_get_clean();
}

geval( 'een beheerder van de klant ziet geen velden', function () {
	$GLOBALS['t']['mediamora'] = false;
	klopt( uitvoer( 'mm_tijdelijk_velden', get_user_by( 'id', 3 ) ), '' );
} );

geval( 'de keuzelijst laat het account zelf weg', function () {
	$html = uitvoer( 'mm_tijdelijk_velden', get_user_by( 'id', 3 ) );
	klopt( false !== strpos( $html, 'name="mm_verloopt_op"' ), true, 'datumveld' );
	klopt( false !== strpos( $html, 'value="nonce-mm_tijdelijk_3"' ), true, 'nonce' );
	klopt( $GLOBALS['t']['dropdown']['exclude'], array( 3 ), 'zonder zichzelf' );
} );

geval( 'een opgeslagen eigen id staat niet voorgeselecteerd', function () {
	$GLOBALS['t']['meta'][3]['mm_overdragen_aan'] = 3;
	uitvoer( 'mm_tijdelijk_velden', get_user_by( 'id', 3 ) );
	klopt( $GLOBALS['t']['dropdown']['selected'], 0 );
} );

geval( 'de opgeslagen datum staat in de tijdzone van de site in het veld', function () {
	verlopen_op( 3, 2, strtotime( '2026-12-15T23:59:59+01:00' ) );
	$html = uitvoer( 'mm_tijdelijk_velden', get_user_by( 'id', 3 ) );
	klopt( false !== strpos( $html, 'value="2026-12-15"' ), true );
} );

geval( 'een Mediamora-adres krijgt geen datumveld', function () {
	$html = uitvoer( 'mm_tijdelijk_velden', get_user_by( 'id', 1 ) );
	klopt( strpos( $html, 'name="mm_verloopt_op"' ), false );
} );

geval( 'Nieuwe gebruiker toont de velden, behalve op een multisite', function () {
	klopt( false !== strpos( uitvoer( 'mm_tijdelijk_velden', 'add-new-user' ), 'name="mm_verloopt_op"' ), true, 'gewoon' );
	$GLOBALS['t']['multisite'] = true;
	klopt( uitvoer( 'mm_tijdelijk_velden', 'add-new-user' ), '', 'multisite' );
} );

geval( 'Nieuwe gebruiker houdt de datum vast na een foutmelding', function () {
	$_POST = array( 'mm_verloopt_op' => '2026-12-15' );
	klopt( false !== strpos( uitvoer( 'mm_tijdelijk_velden', 'add-new-user' ), 'value="2026-12-15"' ), true );
} );

geval( 'het overzicht toont naam, rol, datum en erfgenaam', function () {
	verlopen_op( 3, 2, strtotime( '2030-12-15T23:59:59+01:00' ) );
	$html = uitvoer( 'mm_tijdelijk_overzicht' );
	foreach ( array( 'Marketeer', 'Administrator', '15 December 2030', 'Klant' ) as $tekst ) {
		klopt( false !== strpos( $html, $tekst ), true, $tekst );
	}
} );

geval( 'het overzicht meldt een verlopen account zonder geldige erfgenaam', function () {
	verlopen_op( 3 );
	$html = uitvoer( 'mm_tijdelijk_overzicht' );
	klopt( false !== strpos( $html, 'Geen geldige erfgenaam' ), true, 'erfgenaam' );
	klopt( false !== strpos( $html, 'geblokkeerd' ), true, 'geblokkeerd' );
} );

geval( 'het overzicht zonder accounts zegt dat', function () {
	klopt( false !== strpos( uitvoer( 'mm_tijdelijk_overzicht' ), 'Geen accounts' ), true );
} );

geval( 'Mediamora-adres: hoofdletters tellen niet, subdomeinen en lookalikes niet mee', function () {
	klopt( mm_toolkit_mediamora_adres( 'Hetty@Mediamora.NL' ), true, 'hoofdletters' );
	klopt( mm_toolkit_mediamora_adres( 'a@sub.mediamora.nl' ), false, 'subdomein' );
	klopt( mm_toolkit_mediamora_adres( 'a@nietmediamora.nl' ), false, 'lookalike' );
	klopt( mm_toolkit_mediamora_adres( '' ), false, 'leeg' );
} );


/* -------------------------------------------------------------------------
 * Draaien
 * ---------------------------------------------------------------------- */

$fout = 0;
foreach ( $gevallen as $naam => $functie ) {
	beginstand();
	try {
		$functie();
	} catch ( Throwable $e ) {
		$fout++;
		printf( "FOUT  %s\n      %s\n", $naam, $e->getMessage() );
	}
}

printf( "%d van %d gevallen goed\n", count( $gevallen ) - $fout, count( $gevallen ) );
exit( $fout ? 1 : 0 );
