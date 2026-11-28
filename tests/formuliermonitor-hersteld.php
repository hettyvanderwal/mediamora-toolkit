<?php
/**
 * Mediamora Toolkit, testgevallen voor de Formuliermonitor: de melding
 * "hersteld", het tijdstip van de laatste geslaagde mail en de status voor
 * de sync met MainWP.
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/formuliermonitor-hersteld.php
 * Geeft exitcode 1 als er een geval misgaat.
 *
 * De module verstuurt zijn meldingen met de kale mail() van PHP. Die is
 * niet te vervangen, dus draait dit bestand zichzelf nog een keer in een
 * eigen proces met een sendmail_path dat naar ditzelfde bestand wijst
 * (deel "vang"). Elke melding komt zo als bestand in een tijdelijke map,
 * met onderwerp en koppen zoals mail() ze doorgeeft.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$deel = $argv[1] ?? 'start';

// Vangt één mail van mail() op. Bestaat het bestand "faal" in de map, dan
// mislukt de mail, zoals bij een hosting waar mail() niet werkt.
if ( 'vang' === $deel ) {
	$map = $argv[2];
	if ( file_exists( $map . '/faal' ) ) {
		exit( 1 );
	}
	$nummer = count( glob( $map . '/mail-*.txt' ) ) + 1;
	file_put_contents( sprintf( '%s/mail-%03d.txt', $map, $nummer ), file_get_contents( 'php://stdin' ) );
	exit( 0 );
}

if ( 'start' === $deel ) {
	$map = sys_get_temp_dir() . '/mm-monitor-test-' . getmypid();
	@mkdir( $map, 0777, true );

	$vanger = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' vang ' . escapeshellarg( $map );
	passthru(
		escapeshellarg( PHP_BINARY ) . ' -d ' . escapeshellarg( 'sendmail_path=' . $vanger )
			. ' ' . escapeshellarg( __FILE__ ) . ' binnen ' . escapeshellarg( $map ),
		$code
	);

	array_map( 'unlink', glob( $map . '/*' ) );
	@rmdir( $map );
	exit( $code );
}

/* -------------------------------------------------------------------------
 * Deel "binnen": de module met nagebootste WordPress-functies
 * ---------------------------------------------------------------------- */

$GLOBALS['map'] = $argv[2];

date_default_timezone_set( 'Europe/Amsterdam' );

define( 'ABSPATH', __DIR__ . '/' );
define( 'MM_MONITOR_ONTVANGER', 'hetty@mediamora.nl' );
define( 'MM_TOOLKIT_VERSIE', '1.33.0' );
define( 'MAINWP_CHILD_FILE', __FILE__ );

$GLOBALS['hooks']     = array();
$GLOBALS['opties']    = array();
$GLOBALS['schrijven'] = array(); // optienaam => aantal keer weggeschreven

function add_action( $hook, $callback, $prioriteit = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $hook ][] = $callback;
}
function add_filter( $hook, $callback, $prioriteit = 10, $args = 1 ) {
	add_action( $hook, $callback, $prioriteit, $args );
}
function apply_filters( $hook, $waarde, ...$rest ) {
	foreach ( $GLOBALS['hooks'][ $hook ] ?? array() as $callback ) {
		$waarde = $callback( $waarde, ...$rest );
	}
	return $waarde;
}
function get_option( $naam, $standaard = false ) {
	return array_key_exists( $naam, $GLOBALS['opties'] ) ? $GLOBALS['opties'][ $naam ] : $standaard;
}
function update_option( $naam, $waarde, $autoload = null ) {
	$GLOBALS['opties'][ $naam ] = $waarde;
	$GLOBALS['schrijven'][ $naam ] = ( $GLOBALS['schrijven'][ $naam ] ?? 0 ) + 1;
	return true;
}
function delete_option( $naam ) {
	if ( ! array_key_exists( $naam, $GLOBALS['opties'] ) ) {
		return false;
	}
	unset( $GLOBALS['opties'][ $naam ] );
	return true;
}
function current_time( $formaat ) {
	return date( $formaat );
}
function wp_date( $formaat, $tijd ) {
	return date( $formaat, $tijd );
}
function wp_timezone() {
	return new DateTimeZone( 'Europe/Amsterdam' );
}
function wp_timezone_string() {
	return 'Europe/Amsterdam';
}
function home_url() {
	return 'https://www.voorbeeld.nl';
}
function wp_parse_url( $url, $deel = -1 ) {
	return parse_url( $url, $deel );
}
function is_wp_error( $ding ) {
	return $ding instanceof WP_Error;
}

class WP_Error {
	private $code;
	private $melding;
	private $gegevens;
	public function __construct( $code, $melding, $gegevens = '' ) {
		$this->code     = $code;
		$this->melding  = $melding;
		$this->gegevens = $gegevens;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->melding;
	}
	public function get_error_data() {
		return $this->gegevens;
	}
}

// Uit mediamora-toolkit.php, voor includes/mainwp.php.
$GLOBALS['monitor_laden'] = true;
function mm_toolkit_status() {
	return array(
		'formuliermonitor' => array( 'laden' => $GLOBALS['monitor_laden'] ),
		'anti_spam'        => array( 'laden' => false ),
	);
}
// Voor de formuliertest, die met de module meelaadt (zie tests/formuliertest.php).
function wp_next_scheduled() {
	return false;
}
function wp_unschedule_hook() {
	return 0;
}
function mm_toolkit_huidige_versie() {
	return '1.33.0';
}
function mm_toolkit_onderhoudsklant() {
	return $GLOBALS['monitor_laden'];
}

require dirname( __DIR__ ) . '/modules/formuliermonitor.php';
require dirname( __DIR__ ) . '/includes/mainwp.php';

$fout   = 0;
$totaal = 0;
function klopt( $uitkomst, $verwacht, $wat ) {
	$GLOBALS['totaal']++;
	if ( $uitkomst !== $verwacht ) {
		$GLOBALS['fout']++;
		printf( "FOUT  %s\n      gaf      %s\n      verwacht %s\n", $wat, var_export( $uitkomst, true ), var_export( $verwacht, true ) );
	}
}

/** De opgevangen mails, elk met onderwerp, koppen en tekst. */
function mails() {
	$mails = array();
	foreach ( glob( $GLOBALS['map'] . '/mail-*.txt' ) as $bestand ) {
		$ruw = str_replace( "\r\n", "\n", file_get_contents( $bestand ) );
		list( $kop, $tekst ) = array_pad( explode( "\n\n", $ruw, 2 ), 2, '' );
		$koppen = array();
		foreach ( explode( "\n", $kop ) as $regel ) {
			if ( preg_match( '/^([A-Za-z-]+):\s*(.*)$/', $regel, $m ) ) {
				$koppen[ strtolower( $m[1] ) ] = $m[2];
			}
		}
		$mails[] = array(
			'onderwerp' => $koppen['subject'] ?? '',
			'status'    => $koppen['x-mediamora-status'] ?? '',
			'monitor'   => $koppen['x-mediamora-monitor'] ?? '',
			'tekst'     => $tekst,
		);
	}
	return $mails;
}

function opnieuw() {
	$GLOBALS['opties']    = array();
	$GLOBALS['schrijven'] = array();
	array_map( 'unlink', glob( $GLOBALS['map'] . '/*' ) );
}

function storingsfout() {
	mm_monitor_verwerk_fout(
		new WP_Error( 'wp_mail_failed', 'SMTP Error: The following recipients failed: jan.jansen@voorbeeld.nl', array( 'to' => array( 'Jan <jan.jansen@voorbeeld.nl>' ) ) )
	);
}

$hersteld = '[Formuliermonitor] voorbeeld.nl: hersteld';

// De haken zelf.
klopt( in_array( 'mm_monitor_verwerk_fout', $GLOBALS['hooks']['wp_mail_failed'] ?? array(), true ), true, 'haak op wp_mail_failed' );
klopt( in_array( 'mm_monitor_verwerk_succes', $GLOBALS['hooks']['wp_mail_succeeded'] ?? array(), true ), true, 'haak op wp_mail_succeeded' );
mm_toolkit_mainwp_haak();
klopt( in_array( 'mm_toolkit_mainwp_sync', $GLOBALS['hooks']['mainwp_site_sync_others_data'] ?? array(), true ), true, 'haak op mainwp_site_sync_others_data' );

// 1. Fout, melding, geslaagde mail: precies één herstelmelding, storing dicht.
opnieuw();
storingsfout();
$storing = get_option( MM_MONITOR_OPTIE_STORING );
klopt( is_array( $storing ) ? array( $storing['fouten'], $storing['gemeld'] ) : null, array( 1, true ), '1: storing open en gemeld' );
storingsfout();
klopt( get_option( MM_MONITOR_OPTIE_STORING )['fouten'], 2, '1: tweede fout telt bij dezelfde storing' );
$GLOBALS['opties'][ MM_MONITOR_OPTIE_STORING ]['sinds'] = time() - ( 2 * 3600 + 5 * 60 + 10 );
mm_monitor_verwerk_succes();
mm_monitor_verwerk_succes();
$mails = mails();
klopt( count( $mails ), 2, '1: storingsmelding en één herstelmelding' );
klopt( array_column( $mails, 'onderwerp' ), array( '[Formuliermonitor] voorbeeld.nl', $hersteld ), '1: onderwerpen' );
klopt( array_column( $mails, 'status' ), array( 'storing', 'hersteld' ), '1: kop X-Mediamora-Status' );
klopt( array_column( $mails, 'monitor' ), array( '1', '1' ), '1: kop X-Mediamora-Monitor op allebei' );
klopt( false !== strpos( $mails[1]['tekst'] ?? '', 'Duur:     2 uur en 5 minuten' ), true, '1: duur in de herstelmelding' );
klopt( false !== strpos( $mails[1]['tekst'] ?? '', 'Aantal fouten: 2' ), true, '1: aantal fouten in de herstelmelding' );
klopt( false !== strpos( $mails[1]['tekst'] ?? '', 'Storing sinds: ' . date( 'Y-m-d H:i', time() - 7510 ) ), true, '1: sinds in de herstelmelding' );
klopt( get_option( MM_MONITOR_OPTIE_STORING, 'weg' ), 'weg', '1: storing gesloten' );
klopt( false !== strpos( $mails[0]['tekst'] ?? '', 'jan.jansen' ), false, '1: geen volledig adres in de storingsmelding' );

// 2 en 3. Dezelfde dag na een herstel weer mis: dagcap bereikt, geen melding,
// dus na de volgende geslaagde mail ook geen herstelmelding. De
// herstelmelding van hierboven heeft niet meegeteld voor de dagcap.
klopt( get_option( MM_MONITOR_OPTIE_TELLER )['verstuurd'], 1, '3: herstelmelding telt niet mee voor de dagcap' );
storingsfout();
klopt( count( mails() ), 2, '2: dagcap bereikt, geen nieuwe storingsmelding' );
klopt( get_option( MM_MONITOR_OPTIE_STORING )['gemeld'], false, '2: storing open, niet gemeld' );
mm_monitor_verwerk_succes();
klopt( count( mails() ), 2, '2: geen herstelmelding zonder storingsmelding' );
klopt( get_option( MM_MONITOR_OPTIE_STORING, 'weg' ), 'weg', '2: storing stil gesloten' );

// 3. Met een dagcap van 1 zou een meetellende herstelmelding de volgende
// dag ook niets veranderen; daarom hier ook de dag erna: nieuwe teller,
// nieuwe melding, nieuwe herstelmelding.
$GLOBALS['opties'][ MM_MONITOR_OPTIE_TELLER ]['datum'] = date( 'Y-m-d', time() - 86400 );
storingsfout();
mm_monitor_verwerk_succes();
klopt( array_column( array_slice( mails(), 2 ), 'status' ), array( 'storing', 'hersteld' ), '3: volgende dag weer melding en herstel' );

// Mislukt de storingsmelding zelf, dan is er niets gemeld en komt er ook
// geen herstelmelding.
opnieuw();
touch( $GLOBALS['map'] . '/faal' );
storingsfout();
klopt( get_option( MM_MONITOR_OPTIE_STORING )['gemeld'], false, 'melding mislukt: niet gemeld' );
klopt( get_option( MM_MONITOR_OPTIE_TELLER )['verstuurd'], 0, 'melding mislukt: telt niet voor de dagcap' );
unlink( $GLOBALS['map'] . '/faal' );
mm_monitor_verwerk_succes();
klopt( count( mails() ), 0, 'melding mislukt: geen herstelmelding' );

// 4. Geslaagde mail zonder openstaande storing: geen mail, geen storing.
opnieuw();
mm_monitor_verwerk_succes();
klopt( count( mails() ), 0, '4: geen mail' );
klopt( get_option( MM_MONITOR_OPTIE_STORING, 'weg' ), 'weg', '4: geen storing' );
klopt( array_keys( $GLOBALS['schrijven'] ), array( MM_MONITOR_OPTIE_GELUKT ), '4: alleen de laatste geslaagde mail weggeschreven' );

// 5. Laatste geslaagde mail hooguit één keer per uur weggeschreven.
opnieuw();
for ( $i = 0; $i < 25; $i++ ) {
	mm_monitor_verwerk_succes();
}
klopt( $GLOBALS['schrijven'][ MM_MONITOR_OPTIE_GELUKT ] ?? 0, 1, '5: 25 mails, één keer weggeschreven' );
klopt( abs( get_option( MM_MONITOR_OPTIE_GELUKT ) - time() ) <= 1, true, '5: tijdstip is nu' );
$GLOBALS['opties'][ MM_MONITOR_OPTIE_GELUKT ] = time() - 3599;
mm_monitor_verwerk_succes();
klopt( $GLOBALS['schrijven'][ MM_MONITOR_OPTIE_GELUKT ], 1, '5: binnen het uur niet opnieuw' );
$GLOBALS['opties'][ MM_MONITOR_OPTIE_GELUKT ] = time() - 3600;
mm_monitor_verwerk_succes();
klopt( $GLOBALS['schrijven'][ MM_MONITOR_OPTIE_GELUKT ], 2, '5: na een uur wel' );

// De testknop houdt zijn onderwerp en krijgt status test.
opnieuw();
mm_monitor_verstuur_melding( array( 'tijd' => date( 'Y-m-d H:i:s' ), 'melding' => 'test', 'code' => 'test', 'ontvanger' => '@mediamora.nl' ), 0, 'test' );
klopt( array_column( mails(), 'status' ), array( 'test' ), 'testmelding: kop X-Mediamora-Status' );
klopt( array_column( mails(), 'onderwerp' ), array( '[Formuliermonitor] voorbeeld.nl' ), 'testmelding: onderwerp ongewijzigd' );

// 6. De sync-array: alleen op verzoek, en zonder ongemaskeerde adressen.
opnieuw();
storingsfout();
mm_monitor_verwerk_succes();
// Een regel van voor 1.16.0, met onderwerp en volledige adressen. Er na de
// fout bij gezet, want mm_monitor_schrijf_log() maskeert oude regels al bij
// de volgende fout; zo blijft over of de sync zelf ook maskeert.
$GLOBALS['opties'][ MM_MONITOR_OPTIE_LOG ][] = array(
	'tijd'      => '2025-03-01 10:00:00',
	'melding'   => 'Could not instantiate mail function. Afzender: info@klant.nl',
	'code'      => 'wp_mail_failed',
	'onderwerp' => 'Nieuwe aanvraag van Piet Puk',
	'ontvanger' => 'piet.puk@gmail.com',
);

klopt( apply_filters( 'mainwp_site_sync_others_data', array( 'version' => '6.4.2' ), array() ), array( 'version' => '6.4.2' ), '6: zonder verzoek niets erbij' );

$uit     = apply_filters( 'mainwp_site_sync_others_data', array( 'version' => '6.4.2' ), array( 'mediamora_toolkit' => 1 ) );
$status  = $uit['mediamora_toolkit'] ?? array();
$json    = json_encode( $status );
$monitor = $status['formuliermonitor'] ?? array();

klopt( array_keys( $status ), array( 'schema', 'toolkit_versie', 'onderhoudsklant', 'formuliermonitor', 'anti_spam', 'testinzending' ), '6: sleutels' );
klopt( array( $status['schema'], $status['toolkit_versie'], $status['onderhoudsklant'] ), array( 2, '1.33.0', true ), '6: schema, versie, onderhoudsklant' );
klopt( $status['anti_spam'], null, '6: anti_spam null zonder de module Anti-spam' );
klopt( array( $status['testinzending']['laatste_ronde'], $status['testinzending']['totaal'] ), array( null, 'niet_getest' ), '6: testinzending zonder ronde' );
klopt( array_keys( $monitor ), array( 'aan', 'storing', 'laatste_fout', 'laatste_geslaagde_mail', 'log' ), '6: sleutels formuliermonitor' );
klopt( $monitor['storing'], null, '6: geen storing na herstel' );
klopt( count( $monitor['log'] ), 2, '6: logregels' );
klopt( array_keys( $monitor['log'][0] ), array( 'tijd', 'code', 'melding', 'ontvanger' ), '6: velden per logregel' );
klopt( $monitor['laatste_fout'], $monitor['log'][0]['tijd'], '6: laatste fout is de nieuwste logregel' );
klopt( $monitor['log'][1]['tijd'], '2025-03-01T10:00:00+01:00', '6: tijd met tijdzone' );
klopt( 1 === preg_match( '/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d[+-]\d\d:\d\d$/', (string) $monitor['laatste_geslaagde_mail'] ), true, '6: laatste geslaagde mail als ISO 8601' );
klopt( preg_match( '/[a-z0-9._%+-]@/i', $json ), 0, '6: geen ongemaskeerd adres in de sync' );
klopt( false !== strpos( $json, 'Piet' ) || false !== strpos( $json, 'onderwerp' ), false, '6: geen onderwerp in de sync' );
klopt( false !== strpos( $json, '***@voorbeeld.nl' ) && false !== strpos( $json, '***@gmail.com' ), true, '6: adressen gemaskeerd, domein zichtbaar' );

storingsfout();
$monitor = apply_filters( 'mainwp_site_sync_others_data', array(), array( 'mediamora_toolkit' => 1 ) )['mediamora_toolkit']['formuliermonitor'];
klopt( array_keys( (array) $monitor['storing'] ), array( 'sinds', 'fouten', 'gemeld' ), '6: openstaande storing' );
klopt( array( $monitor['storing']['fouten'], $monitor['storing']['gemeld'] ), array( 1, false ), '6: storing met aantal en gemeld' );

// Formuliermonitor niet geladen: aan op false en verder leeg.
$GLOBALS['monitor_laden'] = false;
$status = apply_filters( 'mainwp_site_sync_others_data', array(), array( 'mediamora_toolkit' => 1 ) )['mediamora_toolkit'];
klopt(
	$status['formuliermonitor'],
	array( 'aan' => false, 'storing' => null, 'laatste_fout' => null, 'laatste_geslaagde_mail' => null, 'log' => array() ),
	'6: monitor uit'
);
klopt( $status['onderhoudsklant'], false, '6: onderhoudsklant uit' );
klopt( array( $status['anti_spam'], $status['testinzending'] ), array( null, null ), '6: monitor uit, anti_spam en testinzending null' );

// Opruimen ruimt ook de nieuwe opties op.
$GLOBALS['opties'][ MM_MONITOR_OPTIE_GELUKT ] = time();
mm_monitor_opruimen();
klopt( $GLOBALS['opties'], array(), 'opruimen: alle opties weg' );

$uninstall = file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );
foreach ( array( MM_MONITOR_OPTIE_TELLER, MM_MONITOR_OPTIE_LOG, MM_MONITOR_OPTIE_STORING, MM_MONITOR_OPTIE_GELUKT ) as $optie ) {
	klopt( false !== strpos( $uninstall, "delete_option( '" . $optie . "' )" ), true, 'uninstall.php: ' . $optie );
}

printf( "%d van %d gevallen goed\n", $totaal - $fout, $totaal );
exit( $fout ? 1 : 0 );
