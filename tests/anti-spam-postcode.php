<?php
/**
 * Mediamora Toolkit, testgevallen voor de postcodecheck van 1.31.0 in de
 * anti-spam: welke velden een postcodeveld zijn, welke postcodes geldig
 * zijn, de melding bij het veld, de reden in de log, de schakelaar en de
 * uitgezonderde velden.
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/anti-spam-postcode.php
 * Geeft exitcode 1 als er een geval misgaat.
 *
 * De instellingen van de module worden per proces één keer gelezen. De
 * delen met de schakelaar uit en met een uitgezonderd veld draaien daarom
 * elk in een eigen proces.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$deel = $argv[1] ?? 'aan';

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/mm-antispam-postcode-' . getmypid() );

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
	$GLOBALS['opties']['mediamora_antispam_settings'] = array( 'check_postcode' => false );
}

if ( 'uitgezonderd' === $deel ) {
	$GLOBALS['opties']['mediamora_antispam_settings'] = array( 'exempt_fields' => array( 'abc1234' => array( 'field_pc' ) ) );
}

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

class Nep_Record {
	public $velden;
	public function __construct( $velden ) {
		$this->velden = $velden;
	}
	public function get_form_settings( $sleutel ) {
		if ( 'id' === $sleutel ) {
			return 'abc1234';
		}
		if ( 'form_fields' === $sleutel ) {
			$lijst = array();
			foreach ( $this->velden as $id => $veld ) {
				$lijst[] = array( 'custom_id' => $id, 'placeholder' => $veld['placeholder'] ?? '' );
			}
			return $lijst;
		}
		return null;
	}
	public function get( $sleutel ) {
		if ( 'fields' !== $sleutel ) {
			return null;
		}
		$velden = array();
		foreach ( $this->velden as $id => $veld ) {
			unset( $veld['placeholder'] );
			$velden[ $id ] = $veld;
		}
		return $velden;
	}
}

class Nep_Handler {
	public $fouten = array();
	public function add_error( $veld, $tekst ) {
		$this->fouten[ $veld ] = $tekst;
	}
}

// Eén veld insturen; geeft terug of het geweigerd is.
function geweigerd( $waarde, $label = 'Postcode', $type = 'text', $id = 'field_1', $placeholder = '' ) {
	$GLOBALS['handler'] = new Nep_Handler();
	mediamora_antispam_validate_form(
		new Nep_Record( array( $id => array( 'type' => $type, 'title' => $label, 'value' => $waarde, 'placeholder' => $placeholder ) ) ),
		$GLOBALS['handler']
	);
	return ! empty( $GLOBALS['handler']->fouten );
}

function laatste_regel( $bestand ) {
	$regels = file( $bestand, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	return $regels ? end( $regels ) : '';
}

if ( 'uit' === $deel ) {

	$test( 'schakelaar uit: zescijferige postcode mag', ! geweigerd( '292100' ) );
	$test( 'schakelaar uit: tekst als postcode mag', ! geweigerd( 'abcdef' ) );

	printf( "%d fout(en)\n", $fout );
	exit( $fout ? 1 : 0 );
}

if ( 'uitgezonderd' === $deel ) {

	$test( 'uitgezonderd veld: zes cijfers mag', ! geweigerd( '292100', 'Postcode', 'text', 'field_pc' ) );
	$test( 'ander veld van hetzelfde formulier wel geweigerd', geweigerd( '292100', 'Postcode', 'text', 'field_ander' ) );

	printf( "%d fout(en)\n", $fout );
	exit( $fout ? 1 : 0 );
}

// --- Geldige postcodes ---

foreach ( array( '2921 AB', '2921AB', '2921ab', '2921 ab', '  2921 AB  ', "2921 AB\n", '1000', '9999', '1000 AA' ) as $postcode ) {
	$test( "geldig: \"$postcode\"", mediamora_antispam_is_valid_postcode( $postcode ) );
	$test( "postcodeveld: \"$postcode\" mag", ! geweigerd( $postcode ) );
}

// --- Ongeldige postcodes ---

foreach ( array( '292100', '123456', '0921 AB', '2921  AB', '2921-AB', '2921 A', '2921 ABC', '29211', '292', 'AB 2921', 'abcdef', 'Krimpen' ) as $postcode ) {
	$test( "ongeldig: \"$postcode\"", ! mediamora_antispam_is_valid_postcode( $postcode ) );
	$test( "postcodeveld: \"$postcode\" geweigerd", geweigerd( $postcode ) );
}

$test( 'leeg postcodeveld wordt overgeslagen', ! geweigerd( '' ) );
$test( 'alleen witruimte wordt overgeslagen', ! geweigerd( '   ' ) );

// --- De melding en de log ---

geweigerd( '292100', 'Postcode', 'text', 'field_pc' );
$test( 'melding bij het veld zelf', isset( $handler->fouten['field_pc'] ) && 'Vul een geldige postcode in, bijvoorbeeld 2921 AB.' === $handler->fouten['field_pc'] );
$test( 'reden ongeldige postcode in de log', false !== strpos( laatste_regel( mediamora_antispam_log_file() ), '| veld: field_pc (text) | reden: ongeldige postcode | waarde: 292100' ) );

// --- Welke velden zijn postcodevelden ---

$test( 'label Postcode', mediamora_antispam_is_postcode_field( 'field_1', 'text', 'Postcode' ) );
$test( 'label POSTCODE *', mediamora_antispam_is_postcode_field( 'field_1', 'text', 'POSTCODE *' ) );
$test( 'label Post code', mediamora_antispam_is_postcode_field( 'field_1', 'text', 'Post code' ) );
$test( 'label Jouw postcode', mediamora_antispam_is_postcode_field( 'field_1', 'text', 'Jouw postcode' ) );
$test( 'veld-ID postcode zonder label', mediamora_antispam_is_postcode_field( 'postcode', 'text', '' ) );
$test( 'veld-ID post_code', mediamora_antispam_is_postcode_field( 'post_code', 'text', '' ) );
$test( 'veld-ID Postcode_klant', mediamora_antispam_is_postcode_field( 'Postcode_klant', 'text', 'Waar woon je?' ) );
$test( 'telefoonveld met label Postcode', mediamora_antispam_is_postcode_field( 'field_1', 'tel', 'Postcode' ) );
$test( 'nummerveld met label Postcode', mediamora_antispam_is_postcode_field( 'field_1', 'number', 'Postcode' ) );
$test( 'nummerveld met zes cijfers geweigerd', geweigerd( '292100', 'Postcode', 'number' ) );
$test( 'geen label, placeholder Postcode', geweigerd( '292100', '', 'text', 'field_2', 'Postcode' ) );
$test( 'label Postcode en woonplaats is geen postcodeveld', ! mediamora_antispam_is_postcode_field( 'field_1', 'text', 'Postcode en woonplaats' ) );
$test( 'label Adres en postcode is geen postcodeveld', ! mediamora_antispam_is_postcode_field( 'field_1', 'text', 'Adres en postcode' ) );
$test( 'Postcode en woonplaats met plaats erin mag', ! geweigerd( '2921 AB Krimpen aan den IJssel', 'Postcode en woonplaats' ) );
$test( 'label Woonplaats is geen postcodeveld', ! mediamora_antispam_is_postcode_field( 'field_1', 'text', 'Woonplaats' ) );
$test( 'label Telefoon is geen postcodeveld', ! mediamora_antispam_is_postcode_field( 'field_1', 'tel', 'Telefoon' ) );
$test( 'textarea is geen postcodeveld', ! mediamora_antispam_is_postcode_field( 'postcode', 'textarea', 'Postcode' ) );
$test( 'keuzelijst is geen postcodeveld', ! mediamora_antispam_is_postcode_field( 'postcode', 'select', 'Postcode' ) );
$test( 'telefoonveld met zes cijfers mag', ! geweigerd( '010123', 'Telefoon', 'tel' ) );

printf( "%d fout(en)\n", $fout );

$uit          = 0;
$uitgezonderd = 0;
passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' uit', $uit );
passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' uitgezonderd', $uitgezonderd );

exit( $fout || $uit || $uitgezonderd ? 1 : 0 );
