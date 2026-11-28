<?php
/**
 * Mediamora Toolkit, testgevallen voor de checks van 1.28.0 in de
 * anti-spam: naamvelden (underscore, cijferreeks, domeinnaam), een
 * berichtveld met alleen een link, website- en profielvelden, en de
 * tijdzone en lengte van de logregels.
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/anti-spam-naamvelden.php
 * Geeft exitcode 1 als er een geval misgaat.
 *
 * De instellingen van de module worden per proces één keer gelezen. Het
 * deel met de schakelaars uit draait daarom in een eigen proces.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$deel = $argv[1] ?? 'aan';

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/mm-antispam-naam-' . getmypid() );

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
	$GLOBALS['opties']['mediamora_antispam_settings'] = array(
		'reject_name_underscore'   => false,
		'reject_name_digits'       => false,
		'reject_name_domain'       => false,
		'reject_textarea_only_url' => false,
	);
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
		$this->fouten[] = $veld;
	}
}

// Eén veld insturen; geeft terug of het geweigerd is.
function geweigerd( $waarde, $label = 'Naam', $type = 'text', $id = 'field_1', $placeholder = '' ) {
	$handler = new Nep_Handler();
	mediamora_antispam_validate_form(
		new Nep_Record( array( $id => array( 'type' => $type, 'title' => $label, 'value' => $waarde, 'placeholder' => $placeholder ) ) ),
		$handler
	);
	return ! empty( $handler->fouten );
}

function laatste_regel( $bestand ) {
	$regels = file( $bestand, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	return $regels ? end( $regels ) : '';
}

$spam_namen = array(
	'crazy_gzpi',
	'tenobet_otEl',
	'mostbet_zlSl',
	'sevencasino_dter',
	'pinco_qzpr',
	'plinko_bnmr',
	'Cleopatra_Taylor',
	'sevencasino_hqmi',
	'NAERTREGE2323839NEYRTHYT',
	'NAYUYUTY1354024NEWETREWT',
	'rick-owens-boutique.ru',
	'onhour.ru',
);

$echte_namen = array( 'Jan-Willem van den Berg', 'Anne-Marie', "O'Brien", 'Ilse Vos', 'Stationstraat', 'J.de Vries', 'A.B. de Jong', 'Lisa' );

if ( 'uit' === $deel ) {

	foreach ( array( 'crazy_gzpi', 'Cleopatra_Taylor', 'onhour.ru', 'NAYUYUTY1354024NEWETREWT' ) as $naam ) {
		$test( "schakelaars uit: $naam niet geweigerd", ! geweigerd( $naam ) );
	}
	$test( 'schakelaar uit: bericht met alleen een link mag', ! geweigerd( 'http://47kg.kr/', 'Bericht', 'textarea' ) );

	printf( "%d fout(en)\n", $fout );
	exit( $fout ? 1 : 0 );
}

// --- Bewijswaarden worden geweigerd ---

foreach ( $spam_namen as $naam ) {
	$test( "naamveld: $naam geweigerd", geweigerd( $naam ) );
}
$test( 'veld-ID name zonder label', geweigerd( 'mostbet_zlSl', '', 'text', 'name' ) );
$test( 'label Voornaam', geweigerd( 'crazy_gzpi', 'Voornaam' ) );
$test( 'label Achternaam', geweigerd( 'crazy_gzpi', 'Achternaam *' ) );
$test( 'label First name', geweigerd( 'crazy_gzpi', 'First name' ) );
$test( 'geen label, placeholder Je naam', geweigerd( 'crazy_gzpi', '', 'text', 'field_2', 'Je naam' ) );
$test( 'domein met http in naamveld', geweigerd( 'http://onhour.ru' ) );
$test( 'domein met www in naamveld', geweigerd( 'www.casino.kr' ) );
$test( 'bericht met alleen een link', geweigerd( 'http://47kg.kr/', 'Bericht', 'textarea' ) );
$test( 'bericht met alleen een link en witruimte', geweigerd( "  https://47kg.kr/ \n", 'Bericht', 'textarea' ) );

// --- Echte namen niet ---

foreach ( $echte_namen as $naam ) {
	$test( "naamveld: $naam mag", ! geweigerd( $naam ) );
}
$test( 'Instagramveld: @hestervanrij mag', ! geweigerd( '@hestervanrij', 'Instagram' ) );
$test( 'Instagramveld: @bovanversendaal mag', ! geweigerd( '@bovanversendaal', 'Instagram-account' ) );
$test( 'Websiteveld: www.blendontbijt.nl mag', ! geweigerd( 'www.blendontbijt.nl', 'Website' ) );
$test( 'Websiteveld: Blendontbijt.nl mag', ! geweigerd( 'Blendontbijt.nl', 'Je website' ) );
$test( 'bericht met tekst en een link mag', ! geweigerd( 'Zie mijn site https://jansenfoto.nl voor de foto\'s', 'Bericht', 'textarea' ) );

// --- Welke velden zijn naamvelden ---

$test( 'Bedrijfsnaam is geen naamveld', ! mediamora_antispam_is_name_field( 'field_b', 'text', 'Bedrijfsnaam', '' ) );
$test( 'E-mail en naam is geen naamveld', ! mediamora_antispam_is_name_field( 'field_b', 'text', 'Naam en e-mailadres', '' ) );
$test( 'Naam van je website is geen naamveld', ! mediamora_antispam_is_name_field( 'field_b', 'text', 'Naam van je website', '' ) );
$test( 'Instagramnaam is geen naamveld', ! mediamora_antispam_is_name_field( 'field_b', 'text', 'Instagramnaam', '' ) );
$test( 'LinkedIn-naam is geen naamveld', ! mediamora_antispam_is_name_field( 'field_b', 'text', 'LinkedIn-naam', '' ) );
$test( 'Gebruikersnaam is geen naamveld', ! mediamora_antispam_is_name_field( 'field_b', 'text', 'Gebruikersnaam', '' ) );
$test( 'veld met url-placeholder is geen naamveld', ! mediamora_antispam_is_name_field( 'name', 'text', 'Naam', 'https://' ) );
$test( 'veld met www-placeholder is geen naamveld', ! mediamora_antispam_is_name_field( 'name', 'text', 'Naam', 'www.jouwsite.nl' ) );
$test( 'textarea is geen naamveld', ! mediamora_antispam_is_name_field( 'name', 'textarea', 'Naam', '' ) );
$test( 'Bedrijfsnaam met underscore mag', ! geweigerd( 'mijn_bedrijf', 'Bedrijfsnaam' ) );
$test( 'Telefoon met cijfers mag', ! geweigerd( '0612345678', 'Telefoonnummer' ) );
$test( 'e-mailadres in placeholder maakt geen url-placeholder', ! mediamora_antispam_has_url_placeholder( 'jan@voorbeeld.nl' ) );

// --- Website- en profielvelden en het vangnet ---

$geen_score = array( 'suspicious' => 1 );
$test( 'profielveld: geen vangnet voor onleesbare tekst', array() === mediamora_antispam_find_near_misses( '@bovanversendaal', 'text', $geen_score, true ) );
$test( 'profielveld: geen vangnet voor link in kort veld', array() === mediamora_antispam_find_near_misses( 'www.gevoelvoorverhalen.nl', 'text', array( 'suspicious' => 0 ), true ) );
$test( 'gewoon veld: vangnet blijft', array() !== mediamora_antispam_find_near_misses( 'Blendontbijt.nl', 'text', array( 'suspicious' => 0 ) ) );
$test( 'label Website is profielveld', mediamora_antispam_is_profile_field( 'Website', '', 'Blendontbijt.nl' ) );
$test( 'label LinkedIn is profielveld', mediamora_antispam_is_profile_field( 'LinkedIn-profiel', '', 'x' ) );
$test( 'url-placeholder is profielveld', mediamora_antispam_is_profile_field( 'Link', 'https://', 'x' ) );
$test( 'waarde met @ is profielveld', mediamora_antispam_is_profile_field( 'Account', '', '@hestervanrij' ) );
$test( 'gewoon veld is geen profielveld', ! mediamora_antispam_is_profile_field( 'Plaats', 'Amsterdam', 'Utrecht' ) );

$nearmiss = mediamora_antispam_nearmiss_file();
@unlink( $nearmiss );
geweigerd( '@bovanversendaal', 'Instagram' );
geweigerd( 'www.gevoelvoorverhalen.nl', 'Website' );
geweigerd( 'Blendontbijt.nl', 'Website' );
$test( 'profielvelden komen niet in near-miss.txt', ! file_exists( $nearmiss ) || '' === trim( file_get_contents( $nearmiss ) ) );

// --- Logregels ---

$log = mediamora_antispam_log_file();
geweigerd( 'mostbet_zlSl' );
$test( 'reden underscore in de log', false !== strpos( laatste_regel( $log ), '| reden: underscore in naamveld |' ) );
geweigerd( 'NAERTREGE2323839NEYRTHYT' );
$test( 'reden cijferreeks in de log', false !== strpos( laatste_regel( $log ), '| reden: cijferreeks in naamveld |' ) );
geweigerd( 'onhour.ru' );
$test( 'reden domeinnaam in de log', false !== strpos( laatste_regel( $log ), '| reden: domeinnaam in naamveld |' ) );
geweigerd( 'http://47kg.kr/', 'Bericht', 'textarea' );
$test( 'reden alleen een link in de log', false !== strpos( laatste_regel( $log ), '| reden: bericht is alleen een link |' ) );

$lang = 'Begin ' . str_repeat( 'a', 300 ) . ' https://een.ru https://twee.ru https://drie.ru';
geweigerd( $lang, 'Bericht', 'textarea' );
$regel = laatste_regel( $log );
// Sinds 1.34.0 staan formulier en kenmerken achter de waarde.
$test( 'logregel heeft 200 tekens van de waarde', 200 === mb_strlen( mediamora_antispam_lees_regel( $regel )['waarde'] ) );

@unlink( $nearmiss );
geweigerd( 'Mijn site is jansen.nl', 'Plaats' );
$regel = laatste_regel( $nearmiss );
$test( 'near-miss krijgt een regel', '' !== $regel );
$test( 'near-miss-regel heeft het tijdstip met tijdzone', (bool) preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} [+-]\d{2}:\d{2} \| /', $regel ) );

$nu = time();
$test( 'logregel heeft het tijdstip met tijdzone', (bool) preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \+0[12]:00 \| /', laatste_regel( $log ) ) );
$test( 'nieuwe logregel wordt goed teruggelezen', abs( mediamora_antispam_parse_line_timestamp( laatste_regel( $log ) ) - $nu ) <= 2 );

// Overstap: een oude regel in UTC en een nieuwe regel van hetzelfde moment
// geven dezelfde tijd, dus het mailvenster telt ze precies zo als eerst.
$t     = 1795000000; // in de wintertijd
$oud   = gmdate( 'Y-m-d H:i:s', $t ) . ' | veld: x (text) | reden: test | waarde: oud';
$nieuw = mediamora_antispam_log_time( $t ) . ' | veld: x (text) | reden: test | waarde: nieuw';
$test( 'oude regel zonder tijdzone telt als UTC', $t === mediamora_antispam_parse_line_timestamp( $oud ) );
$test( 'nieuwe regel met tijdzone', $t === mediamora_antispam_parse_line_timestamp( $nieuw ) );
$test( 'nieuwe regel staat in de tijd van de site', 0 === strpos( $nieuw, gmdate( 'Y-m-d H:i:s', $t + HOUR_IN_SECONDS ) . ' +01:00' ) );
$zomer = 1783000000;
$test( 'zomertijd krijgt +02:00', 0 === strpos( mediamora_antispam_log_time( $zomer ), gmdate( 'Y-m-d H:i:s', $zomer + 2 * HOUR_IN_SECONDS ) . ' +02:00' ) );
$test( 'oude regel komt in de mail in de tijd van de site', mediamora_antispam_line_for_mail( $oud ) === str_replace( 'nieuw', 'oud', $nieuw ) );
$test( 'nieuwe regel blijft in de mail gelijk', mediamora_antispam_line_for_mail( $nieuw ) === $nieuw );
$test( 'onleesbare regel blijft in de mail gelijk', 'kapot' === mediamora_antispam_line_for_mail( 'kapot' ) );

// Het mailvenster van een mail van vóór de update: >= begin en < nu, voor
// oude en nieuwe regels op precies dezelfde grenzen.
$begin = mediamora_antispam_mail_window_start( $t, $t + DAY_IN_SECONDS, DAY_IN_SECONDS, 30 * DAY_IN_SECONDS );
$test( 'mailvenster begint bij de vorige mail', $t === $begin );
foreach ( array( $t - 1 => false, $t => true, $t + DAY_IN_SECONDS - 1 => true, $t + DAY_IN_SECONDS => false ) as $tijd => $erin ) {
	foreach ( array( gmdate( 'Y-m-d H:i:s', $tijd ), mediamora_antispam_log_time( $tijd ) ) as $stempel ) {
		$ts = mediamora_antispam_parse_line_timestamp( $stempel . ' | x' );
		$test( "venster: $stempel " . ( $erin ? 'erin' : 'erbuiten' ), $erin === ( $ts >= $begin && $ts < $t + DAY_IN_SECONDS ) );
	}
}

printf( "%d fout(en)\n", $fout );

$uit = 0;
passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' uit', $uit );

exit( $fout || $uit ? 1 : 0 );
