<?php
/**
 * Mediamora Toolkit, testgevallen voor de module Afsluiten op einddatum.
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/einddatum.php
 * Geeft exitcode 1 als er een geval misgaat.
 *
 * De WordPress-functies hieronder zijn kale nabootsingen: opties en
 * transients in een array, plugins als lijst met kop en stand, en een cron
 * met tijdstip per hook. Genoeg om de volgorde, het slot, het herhalen en de
 * mail na te lopen.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'WP_PLUGIN_DIR', '/tmp/plugins' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'MM_TOOLKIT_BESTAND', WP_PLUGIN_DIR . '/mediamora-toolkit/mediamora-toolkit.php' );
define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/mm-einddatum-test-' . getmypid() );
define( 'MM_TOOLKIT_MAP', WP_CONTENT_DIR . '/toolkit' );
define( 'MM_MONITOR_ONTVANGER', 'meldingen@voorbeeld.nl' );

class WP_Error {
	private $m;
	public function __construct( $c = '', $m = '' ) { $this->m = $m; }
	public function get_error_message() { return $this->m; }
}

class Nep_WPDB {
	public $options     = 'wp_options';
	public $base_prefix = 'wp_';
	public function get_col( $sql ) {
		$uit = array();
		foreach ( array_keys( $GLOBALS['t']['opties'] ) as $naam ) {
			if ( 0 === strpos( $naam, 'wpvivid_' ) ) {
				$uit[] = $naam;
			}
		}
		return $uit;
	}
	public function prepare( $sql, $waarde ) { return str_replace( '%s', "'" . $waarde . "'", $sql ); }
	public function esc_like( $tekst ) { return $tekst; }
	public function get_var( $sql ) { return $GLOBALS['t']['tabel'] ? 'wp_wpvivid_options' : null; }
	public function get_results( $sql ) {
		return $GLOBALS['t']['tabel'] ? $GLOBALS['t']['tabelrijen'] : array();
	}
	public function update( $tabel, $data, $waar ) {
		foreach ( $GLOBALS['t']['tabelrijen'] as $i => $rij ) {
			if ( $rij['option_name'] === $waar['option_name'] ) {
				$GLOBALS['t']['tabelrijen'][ $i ]['option_value'] = $data['option_value'];
			}
		}
		return 1;
	}
	public function query( $sql ) {
		if ( false !== strpos( $sql, 'DROP TABLE' ) ) {
			$GLOBALS['t']['tabel'] = false;
		}
		return true;
	}
}
$GLOBALS['wpdb'] = new Nep_WPDB();

function add_action() {}
function maybe_unserialize( $w ) { $u = @unserialize( $w ); return false === $u && 'b:0;' !== $w ? $w : $u; }
function maybe_serialize( $w ) { return is_array( $w ) || is_object( $w ) ? serialize( $w ) : $w; }
function is_multisite() { return false; }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function wp_timezone() { return new DateTimeZone( 'Europe/Amsterdam' ); }
function wp_timezone_string() { return 'Europe/Amsterdam'; }
function wp_date( $formaat, $moment = null ) {
	if ( null === $moment && 'Y-m-d' === $formaat ) {
		return $GLOBALS['t']['vandaag'];
	}
	$d = new DateTime( '@' . ( null === $moment ? time() : $moment ) );
	$d->setTimezone( wp_timezone() );
	return $d->format( $formaat );
}
function home_url() { return 'https://www.klant.nl'; }
function wp_parse_url( $url, $deel ) { return parse_url( $url, $deel ); }
function plugin_basename( $bestand ) { return substr( $bestand, strlen( WP_PLUGIN_DIR ) + 1 ); }
function wp_cache_delete() {}

function get_option( $naam, $standaard = false ) { return array_key_exists( $naam, $GLOBALS['t']['opties'] ) ? $GLOBALS['t']['opties'][ $naam ] : $standaard; }
function update_option( $naam, $waarde ) { $GLOBALS['t']['opties'][ $naam ] = $waarde; return true; }
function delete_option( $naam ) {
	$was = array_key_exists( $naam, $GLOBALS['t']['opties'] );
	unset( $GLOBALS['t']['opties'][ $naam ] );
	return $was;
}
function get_transient( $naam ) { return isset( $GLOBALS['t']['transients'][ $naam ] ) ? $GLOBALS['t']['transients'][ $naam ] : false; }
function set_transient( $naam, $waarde ) { $GLOBALS['t']['transients'][ $naam ] = $waarde; return true; }
function delete_transient( $naam ) { unset( $GLOBALS['t']['transients'][ $naam ] ); return true; }

function wp_next_scheduled( $hook ) { return isset( $GLOBALS['t']['cron'][ $hook ] ) ? $GLOBALS['t']['cron'][ $hook ] : false; }
function wp_schedule_event( $moment, $herhaling, $hook ) { $GLOBALS['t']['cron'][ $hook ] = $moment; return true; }
function wp_schedule_single_event( $moment, $hook ) { $GLOBALS['t']['cron'][ $hook ] = $moment; return true; }
function wp_unschedule_hook( $hook ) { unset( $GLOBALS['t']['cron'][ $hook ] ); return 1; }
function _get_cron_array() {
	$uit = array();
	foreach ( $GLOBALS['t']['cron'] as $hook => $moment ) {
		$uit[ $moment ][ $hook ] = array();
	}
	return $uit;
}

function get_plugins() {
	$uit = array();
	foreach ( $GLOBALS['t']['plugins'] as $bestand => $p ) {
		$uit[ $bestand ] = array( 'Name' => $p['naam'], 'Version' => '1.0' );
	}
	return $uit;
}
function is_plugin_active( $bestand ) { return ! empty( $GLOBALS['t']['plugins'][ $bestand ]['actief'] ); }
function deactivate_plugins( $bestanden ) {
	foreach ( (array) $bestanden as $b ) {
		$GLOBALS['t']['log'][] = 'deactiveer ' . $b;
		if ( isset( $GLOBALS['t']['plugins'][ $b ] ) && empty( $GLOBALS['t']['plugins'][ $b ]['vast'] ) ) {
			$GLOBALS['t']['plugins'][ $b ]['actief'] = false;
		}
	}
}
function delete_plugins( $bestanden ) {
	foreach ( $bestanden as $b ) {
		$GLOBALS['t']['log'][] = 'verwijder ' . $b;
		if ( ! empty( $GLOBALS['t']['plugins'][ $b ]['weigert'] ) ) {
			return new WP_Error( 'x', 'kon de map niet weghalen' );
		}
		unset( $GLOBALS['t']['plugins'][ $b ] );
	}
	return true;
}
function get_filesystem_method() { return $GLOBALS['t']['methode']; }
function size_format( $bytes ) { return $bytes . ' B'; }
function wp_is_writable( $pad ) { return ! in_array( $pad, $GLOBALS['t']['niet_schrijfbaar'], true ); }
function rm_map( $pad ) {
	if ( ! is_dir( $pad ) || is_link( $pad ) ) { @unlink( $pad ); return; }
	foreach ( scandir( $pad ) as $f ) { if ( '.' !== $f && '..' !== $f ) rm_map( $pad . '/' . $f ); }
	@rmdir( $pad );
}
class Nep_FS {
	public function delete( $pad, $recursief, $soort ) {
		$GLOBALS['t']['log'][] = 'map weg ' . basename( $pad );
		if ( empty( $GLOBALS['t']['map_weigert'] ) ) { rm_map( $pad ); }
		return true;
	}
}
function WP_Filesystem() { $GLOBALS['wp_filesystem'] = new Nep_FS(); return true; }

function wp_mail( $aan, $onderwerp, $tekst ) {
	$GLOBALS['t']['log'][] = 'mail';
	if ( ! $GLOBALS['t']['mail_lukt'] ) {
		return false;
	}
	$GLOBALS['t']['mails'][] = array( 'aan' => $aan, 'onderwerp' => $onderwerp, 'tekst' => $tekst );
	return true;
}
require dirname( __DIR__ ) . '/modules/einddatum.php';

$fouten = 0;
function klopt( $echt, $verwacht, $wat ) {
	global $fouten;
	if ( $echt === $verwacht ) {
		echo "  ok   $wat\n";
		return;
	}
	$fouten++;
	echo "  FOUT $wat\n       verwacht: " . var_export( $verwacht, true ) . "\n       kreeg:    " . var_export( $echt, true ) . "\n";
}

/**
 * Een site met de vier plugins, WPvivid ingericht en de einddatum bereikt.
 */
function begin( $extra = array() ) {
	rm_map( WP_CONTENT_DIR );
	mkdir( WP_CONTENT_DIR . '/wpvividbackups/wpvivid_log', 0777, true );
	file_put_contents( WP_CONTENT_DIR . '/wpvividbackups/klant_2026_db.zip', 'GEHEIM-in-dump' );
	mkdir( WP_CONTENT_DIR . '/uploads', 0777, true );
	mkdir( MM_TOOLKIT_MAP . '/modules', 0777, true );
	$GLOBALS['t'] = array_merge(
		array(
			'niet_schrijfbaar' => array(),
			'map_weigert'      => false,
			'vandaag'    => '2026-12-01',
			'methode'    => 'direct',
			'mail_lukt'  => true,
			'mails'      => array(),
			'log'        => array(),
			'tabel'      => true,
			'tabelrijen' => array(
				array( 'option_name' => 'pro_taak', 'option_value' => serialize( array( 'options' => array( 'remote_options' => array( 'x' => array( 'type' => 'amazons3', 'secret' => 'GEHEIM' ) ) ) ) ) ),
				array( 'option_name' => 'melding', 'option_value' => 'gewone tekst' ),
			),
			'transients' => array(),
			'cron'       => array(
				'wpvivid_main_schedule_event'      => 100,
				'wpvivid_schedule_event6a05adadd1' => 200,
				'mm_einddatum_controle'            => 300,
				'wp_version_check'                 => 400,
				'incr_bestanden_x1'                => 500,
			),
			'plugins'    => array(
				'wpvivid-backup-pro/wpvivid-backup-pro.php'       => array( 'naam' => 'WPvivid Plugins Pro', 'actief' => true ),
				'wpvivid-backuprestore/wpvivid-backuprestore.php' => array( 'naam' => 'WPvivid Backup Plugin', 'actief' => true ),
				'mainwp-child-reports/mainwp-child-reports.php'   => array( 'naam' => 'MainWP Child Reports', 'actief' => true ),
				// In een andere map: alleen op de naam te vinden.
				'mainwp-child-2/mainwp-child.php'                 => array( 'naam' => 'MainWP Child', 'actief' => true ),
				'mediamora-toolkit/mediamora-toolkit.php'         => array( 'naam' => 'Mediamora Toolkit', 'actief' => true ),
				'elementor/elementor.php'                         => array( 'naam' => 'Elementor', 'actief' => true ),
			),
			'opties'     => array(
				'mm_einddatum'                   => '2026-12-01',
				'wpvivid_upload_setting'         => array(
					'wpvivid-remote-6a05adadd1983' => array( 'type' => 'amazons3', 'name' => 'MediamoraS3', 'path' => 'klant_nl', 'access' => 'GEHEIM', 'secret' => 'GEHEIM' ),
					'iets_anders'                  => 'blijft',
				),
				'wpvivid_user_history'           => array( 'remote_selected' => array( 'wpvivid-remote-6a05adadd1983' ) ),
				'wpvivid_schedule_setting'       => array( 'enable' => true ),
				'wpvivid_schedule_addon_setting' => array( 'x' => array( 'status' => 'Active' ) ),
				'wpvivid_pro_user'               => array( 'token' => 'GEHEIM' ),
				'wpvivid_connect_key'            => 'GEHEIM-verbinding',
				'wpvivid_incremental_schedules'  => array( 'x1' => array( 'id' => 'x1', 'files_schedule_id' => 'incr_bestanden_x1', 'db_schedule_id' => 'incr_db_x1' ) ),
				'wpvivid_new_remote_list'        => array( 'wpvivid-remote-6a05adadd1983' => array( 'wpvivid-811c11ebe6aba' => array( 'remote' => array( 'wpvivid-remote-6a05adadd1983' => array( 'type' => 'amazons3', 'access' => 'GEHEIM', 'secret' => 'GEHEIM', 'name' => 'MediamoraS3' ) ) ) ) ),
				'wpvivid_dashboard_info'         => array( 'plugins' => array( 1, 2, 3, 4, 5 ) ),
				'wpvivid_common_setting'         => array( 'log_save_location' => 'x' ),
				'wpvivid_backup_list'            => array(
					'taak1' => array(
						'type'   => 'Manual',
						'backup' => array( 'files' => array( 'a.zip' ) ),
						'remote' => array( 'wpvivid-remote-6a05adadd1983' => array( 'type' => 'amazons3', 'name' => 'MediamoraS3', 'access' => 'GEHEIM', 'secret' => 'GEHEIM' ) ),
					),
				),
				'wpvivid_task_list'              => array( 'taak2' => array( 'options' => array( 'remote_options' => array( 'r' => array( 'type' => 'amazons3', 'secret' => 'GEHEIM' ) ) ) ) ),
				'elementor_version'              => '3.0',
			),
		),
		$extra
	);
}

function heeft_geheim() {
	return false !== strpos( serialize( $GLOBALS['t']['opties'] ), 'GEHEIM' )
		|| ( $GLOBALS['t']['tabel'] && false !== strpos( serialize( $GLOBALS['t']['tabelrijen'] ), 'GEHEIM' ) );
}

// --- Datum -----------------------------------------------------------------
echo "Datum\n";
klopt( mm_einddatum_geldig( '2026-12-01' ), true, 'geldige datum' );
klopt( mm_einddatum_geldig( '2026-02-30' ), false, '30 februari bestaat niet' );
klopt( mm_einddatum_geldig( '1-12-2026' ), false, 'verkeerde vorm' );
klopt( mm_einddatum_geldig( '' ), false, 'leeg' );
begin( array( 'vandaag' => '2026-11-30' ) );
klopt( mm_einddatum_bereikt( '2026-12-01' ), false, 'dag ervoor: niet bereikt' );
$GLOBALS['t']['vandaag'] = '2026-12-01';
klopt( mm_einddatum_bereikt( '2026-12-01' ), true, 'op de dag zelf: bereikt' );
klopt( mm_einddatum_bereikt( '' ), false, 'zonder datum: nooit' );

// --- Herkennen -------------------------------------------------------------
echo "Herkennen\n";
begin();
$g = mm_einddatum_plugins_zoeken();
klopt( array_keys( $g['mainwp_child'] ), array( 'mainwp-child-2/mainwp-child.php' ), 'MainWP Child in een andere map, op naam' );
klopt( array_keys( $g['mainwp_child_reports'] ), array( 'mainwp-child-reports/mainwp-child-reports.php' ), 'Child Reports niet verward met Child' );
$GLOBALS['t']['plugins']['wpvivid-backuprestore/wpvivid-backuprestore.php']['naam'] = 'Iets anders';
$g = mm_einddatum_plugins_zoeken();
klopt( array_keys( $g['wpvivid'] ), array( 'wpvivid-backuprestore/wpvivid-backuprestore.php' ), 'op het vaste bestand als de naam anders is' );
$alle = array();
foreach ( $g as $b ) {
	$alle = array_merge( $alle, array_keys( $b ) );
}
klopt( in_array( 'mediamora-toolkit/mediamora-toolkit.php', $alle, true ) || in_array( 'elementor/elementor.php', $alle, true ), false, 'toolkit en andere plugins doen niet mee' );

// --- Niet uitvoeren --------------------------------------------------------
echo "Niet uitvoeren\n";
begin( array( 'vandaag' => '2026-11-30' ) );
mm_einddatum_uitvoeren();
klopt( $GLOBALS['t']['log'], array(), 'voor de einddatum gebeurt niets' );
begin();
$GLOBALS['t']['transients']['mm_einddatum_slot'] = 1;
mm_einddatum_uitvoeren();
klopt( $GLOBALS['t']['log'], array(), 'met een slot gebeurt niets' );
klopt( heeft_geheim(), true, 'en blijft WPvivid staan' );

// --- Gewone afsluiting -----------------------------------------------------
echo "Gewone afsluiting\n";
begin();
mm_einddatum_uitvoeren();
klopt(
	$GLOBALS['t']['log'],
	array(
		'map weg wpvividbackups',
		'deactiveer wpvivid-backup-pro/wpvivid-backup-pro.php',
		'verwijder wpvivid-backup-pro/wpvivid-backup-pro.php',
		'deactiveer wpvivid-backuprestore/wpvivid-backuprestore.php',
		'verwijder wpvivid-backuprestore/wpvivid-backuprestore.php',
		'deactiveer mainwp-child-reports/mainwp-child-reports.php',
		'verwijder mainwp-child-reports/mainwp-child-reports.php',
		'deactiveer mainwp-child-2/mainwp-child.php',
		'verwijder mainwp-child-2/mainwp-child.php',
	),
	'eerste ronde: back-ups weg, in volgorde deactiveren en verwijderen, nog geen mail'
);
klopt( heeft_geheim(), false, 'S3-sleutels en Pro-token weg' );
klopt( get_option( 'wpvivid_upload_setting' ), array( 'iets_anders' => 'blijft' ), 'wat geen remote is blijft in wpvivid_upload_setting' );
klopt( get_option( 'wpvivid_user_history' ), array( 'remote_selected' => array() ), 'geen gekozen remote meer' );
klopt( get_option( 'wpvivid_schedule_addon_setting', 'weg' ), 'weg', 'schema van Pro weg' );
klopt( array_keys( get_option( 'wpvivid_backup_list' )['taak1'] ), array( 'type', 'backup', 'remote' ), 'back-uplijst blijft, zonder de kopie' );
klopt( get_option( 'wpvivid_backup_list' )['taak1']['remote'], array(), 'kopie uit de back-uplijst weg' );
klopt( get_option( 'wpvivid_task_list' )['taak2']['options']['remote_options'], false, 'kopie uit de takenlijst weg' );
klopt( $GLOBALS['t']['tabelrijen'][1]['option_value'], 'gewone tekst', 'andere rijen in wpvivid_options ongemoeid' );
klopt( wp_next_scheduled( 'wpvivid_main_schedule_event' ), false, 'event van WPvivid afgemeld' );
klopt( wp_next_scheduled( 'wpvivid_schedule_event6a05adadd1' ), false, 'event van het Pro-schema afgemeld' );
klopt( wp_next_scheduled( 'wp_version_check' ), 400, 'andere events blijven staan' );
klopt( wp_next_scheduled( 'incr_bestanden_x1' ), false, 'incrementeel schema afgemeld op zijn ID' );
klopt( get_option( 'wpvivid_connect_key', 'weg' ), 'weg', 'verbindingssleutel Pro weg' );
klopt( (bool) wp_next_scheduled( 'mm_einddatum_vervolg' ), true, 'vervolgronde ingepland' );
klopt( get_transient( 'mm_einddatum_slot' ), false, 'slot weer vrij' );
klopt( isset( $GLOBALS['t']['plugins']['mediamora-toolkit/mediamora-toolkit.php'] ), true, 'toolkit staat er nog' );

$GLOBALS['t']['log'] = array();
mm_einddatum_uitvoeren();
klopt( count( $GLOBALS['t']['mails'] ), 1, 'vervolgronde: één mail' );
$mail = $GLOBALS['t']['mails'][0];
klopt( $mail['aan'], 'meldingen@voorbeeld.nl', 'naar het meldingsadres van de toolkit' );
klopt( $mail['onderwerp'], '[Einddatum onderhoud] klant.nl', 'onderwerp met het domein zonder www' );
$regels = explode( "\n", $mail['tekst'] );
klopt( end( $regels ), 'Haal de site uit het MainWP-dashboard en zet het klantrecord in de Projecten-tool op pakket geen.', 'laatste regel' );
klopt( false !== strpos( $mail['tekst'], '- MainWP Child: verwijderd' ), true, 'MainWP Child verwijderd in de mail' );
klopt( false !== strpos( $mail['tekst'], 'Wissen in WPvivid: gelukt' ), true, 'wissen gelukt in de mail' );
klopt( false !== strpos( $mail['tekst'], 'MediamoraS3 (Amazon S3, map klant_nl)' ), true, 'remote met map in de mail' );
klopt( false !== strpos( $mail['tekst'], 'GEHEIM' ), false, 'geen sleutels in de mail' );
klopt( get_option( 'wpvivid_common_setting', 'weg' ), 'weg', 'overige WPvivid-opties opgeruimd' );
klopt( $GLOBALS['t']['tabel'], false, 'tabel wpvivid_options weg' );
klopt( array_slice( $GLOBALS['t']['log'], -3 ), array( 'mail', 'deactiveer mediamora-toolkit/mediamora-toolkit.php', 'verwijder mediamora-toolkit/mediamora-toolkit.php' ), 'eerst de mail, dan de toolkit' );
klopt( is_dir( WP_CONTENT_DIR . '/wpvividbackups' ), false, 'lokale back-ups weg' );
klopt( is_dir( WP_CONTENT_DIR . '/uploads' ), true, 'uploads blijft staan' );
klopt( false !== strpos( $mail['tekst'], '- Lokale back-ups verwijderd: wp-content/wpvividbackups (1 bestand, 14 B)' ), true, 'back-ups met omvang in de mail' );

// --- Plugins al weg ----------------------------------------------------------
echo "Plugins er niet\n";
begin();
foreach ( array( 'wpvivid-backup-pro/wpvivid-backup-pro.php', 'mainwp-child-reports/mainwp-child-reports.php' ) as $b ) {
	unset( $GLOBALS['t']['plugins'][ $b ] );
}
$GLOBALS['t']['plugins']['wpvivid-backuprestore/wpvivid-backuprestore.php']['actief'] = false;
$GLOBALS['t']['plugins']['mainwp-child-2/mainwp-child.php']['actief']                 = false;
mm_einddatum_uitvoeren();
$tekst = isset( $GLOBALS['t']['mails'][0] ) ? $GLOBALS['t']['mails'][0]['tekst'] : '';
klopt( false !== strpos( $tekst, '- WPvivid Plugins Pro: niet gevonden' ), true, 'niet gevonden in de mail' );
klopt( false !== strpos( $tekst, '- WPvivid Backup Plugin: verwijderd' ), true, 'niet actief: zonder vervolgronde meteen verwijderd en gemaild' );
klopt( isset( $GLOBALS['t']['plugins']['mediamora-toolkit/mediamora-toolkit.php'] ), false, 'toolkit weg' );

// --- Geen directe schrijftoegang -------------------------------------------
echo "Geen direct\n";
begin( array( 'methode' => 'ftpext' ) );
mm_einddatum_uitvoeren();
mm_einddatum_uitvoeren();
klopt( count( $GLOBALS['t']['mails'] ), 1, 'één mail' );
$tekst = isset( $GLOBALS['t']['mails'][0] ) ? $GLOBALS['t']['mails'][0]['tekst'] : '';
klopt( false !== strpos( $tekst, '- MainWP Child: alleen gedeactiveerd, niet verwijderd' ), true, 'gedeactiveerd in de mail' );
klopt( false !== strpos( $tekst, 'geen \'direct\'-methode' ), true, 'uitleg in de mail' );
klopt( false !== strpos( $tekst, 'Wissen in WPvivid: gelukt' ), true, 'wissen toch gelukt' );
klopt( isset( $GLOBALS['t']['plugins']['mainwp-child-2/mainwp-child.php'] ), true, 'plugin staat er nog' );
klopt( is_dir( WP_CONTENT_DIR . '/wpvividbackups' ), true, 'back-ups blijven zonder schrijftoegang staan' );
klopt( false !== strpos( $tekst, '- Lokale back-ups NIET verwijderd: wp-content/wpvividbackups' ), true, 'en dat staat in de mail' );
klopt( heeft_geheim(), false, 'ook zonder verwijderen geen S3-gegevens meer, ook geen kopieën' );
klopt( isset( $GLOBALS['t']['plugins']['mediamora-toolkit/mediamora-toolkit.php'] ), true, 'toolkit blijft staan' );
klopt( in_array( 'verwijder mainwp-child-2/mainwp-child.php', $GLOBALS['t']['log'], true ), false, 'niets verwijderd' );
// De volgende nacht: dezelfde uitkomst, geen nieuwe mail.
mm_einddatum_uitvoeren();
klopt( count( $GLOBALS['t']['mails'] ), 1, 'volgende nacht dezelfde uitkomst: geen tweede mail' );
// Daarna wel schrijftoegang.
$GLOBALS['t']['methode'] = 'direct';
mm_einddatum_uitvoeren();
klopt( count( $GLOBALS['t']['mails'] ), 2, 'met schrijftoegang: nieuwe mail' );
klopt( isset( $GLOBALS['t']['plugins']['mediamora-toolkit/mediamora-toolkit.php'] ), false, 'en daarna de toolkit weg' );

// --- Mail mislukt ----------------------------------------------------------
echo "Mail mislukt\n";
begin( array( 'mail_lukt' => false ) );
mm_einddatum_uitvoeren();
// Na wp_mail() probeert de module mail() van PHP. Alleen testen waar die niet
// echt kan versturen.
if ( ! function_exists( 'mail' ) || ! @ini_get( 'sendmail_path' ) || ! is_executable( strtok( (string) ini_get( 'sendmail_path' ), ' ' ) ) ) {
	mm_einddatum_uitvoeren();
	klopt( isset( $GLOBALS['t']['plugins']['mediamora-toolkit/mediamora-toolkit.php'] ), true, 'zonder mail blijft de toolkit staan' );
	$GLOBALS['t']['mail_lukt'] = true;
	mm_einddatum_uitvoeren();
	klopt( count( $GLOBALS['t']['mails'] ), 1, 'volgende ronde wel mail' );
	klopt( isset( $GLOBALS['t']['plugins']['mediamora-toolkit/mediamora-toolkit.php'] ), false, 'en dan de toolkit weg' );
} else {
	echo "  --   overgeslagen: deze PHP kan echt mailen\n";
}

// --- Verwijderen mislukt ---------------------------------------------------
echo "Verwijderen mislukt\n";
begin();
$GLOBALS['t']['plugins']['mainwp-child-2/mainwp-child.php']['weigert'] = true;
mm_einddatum_uitvoeren();
mm_einddatum_uitvoeren();
$tekst = isset( $GLOBALS['t']['mails'][0] ) ? $GLOBALS['t']['mails'][0]['tekst'] : '';
klopt( false !== strpos( $tekst, '- MainWP Child: mislukt: kon de map niet weghalen' ), true, 'mislukt met melding in de mail' );
klopt( isset( $GLOBALS['t']['plugins']['mediamora-toolkit/mediamora-toolkit.php'] ), true, 'toolkit blijft staan' );

// --- Eigen back-upmap -------------------------------------------------------
echo "Eigen back-upmap\n";
begin();
mkdir( WP_CONTENT_DIR . '/mijnbackups' );
file_put_contents( WP_CONTENT_DIR . '/mijnbackups/a.zip', 'x' );
$GLOBALS['t']['opties']['wpvivid_local_setting'] = array( 'path' => 'mijnbackups' );
klopt( array_keys( mm_einddatum_backupmappen() ), array( 'wpvividbackups', 'mijnbackups' ), 'standaardmap en eigen map gevonden' );
$GLOBALS['t']['opties']['wpvivid_local_setting'] = array( 'path' => 'uploads' );
klopt( array_keys( mm_einddatum_backupmappen() ), array( 'wpvividbackups' ), 'nooit uploads' );
$GLOBALS['t']['opties']['wpvivid_local_setting'] = array( 'path' => '../../etc' );
klopt( array_keys( mm_einddatum_backupmappen() ), array( 'wpvividbackups' ), 'nooit buiten wp-content' );
$GLOBALS['t']['opties']['wpvivid_local_setting'] = array( 'path' => 'mijnbackups' );
mm_einddatum_uitvoeren();
mm_einddatum_uitvoeren();
klopt( is_dir( WP_CONTENT_DIR . '/mijnbackups' ), false, 'eigen map weg' );

// --- Toolkit niet te verwijderen ---------------------------------------------
echo "Toolkit niet te verwijderen\n";
begin();
$GLOBALS['t']['niet_schrijfbaar'] = array( MM_TOOLKIT_MAP . '/modules' );
mm_einddatum_uitvoeren();
mm_einddatum_uitvoeren();
$tekst = isset( $GLOBALS['t']['mails'][0] ) ? $GLOBALS['t']['mails'][0]['tekst'] : '';
klopt( false !== strpos( $tekst, 'kan zichzelf niet verwijderen' ), true, 'mail zegt dat de toolkit zelf weg moet' );
klopt( isset( $GLOBALS['t']['plugins']['mediamora-toolkit/mediamora-toolkit.php'] ), true, 'toolkit niet uitgezet of verwijderd' );
klopt( in_array( 'deactiveer mediamora-toolkit/mediamora-toolkit.php', $GLOBALS['t']['log'], true ), false, 'ook niet gedeactiveerd' );
mm_einddatum_uitvoeren();
klopt( count( $GLOBALS['t']['mails'] ), 1, 'volgende nacht geen tweede mail' );

// --- Verwijderen van de toolkit mislukt ---------------------------------------
echo "Verwijderen toolkit mislukt\n";
begin();
$GLOBALS['t']['plugins']['mediamora-toolkit/mediamora-toolkit.php']['weigert'] = true;
mm_einddatum_uitvoeren();
mm_einddatum_uitvoeren();
klopt( count( $GLOBALS['t']['mails'] ), 2, 'eindmail en daarna een aparte mail' );
$tweede = isset( $GLOBALS['t']['mails'][1] ) ? $GLOBALS['t']['mails'][1] : array( 'onderwerp' => '', 'tekst' => '' );
klopt( $tweede['onderwerp'], '[Einddatum onderhoud] klant.nl: toolkit niet verwijderd', 'onderwerp van de aparte mail' );
klopt( false !== strpos( $tweede['tekst'], 'kon de map niet weghalen' ), true, 'met de melding' );

// --- Proefdraai ------------------------------------------------------------
echo "Proefdraai\n";
begin();
$voor = $GLOBALS['t'];
$p    = mm_einddatum_proefdraai();
klopt( $GLOBALS['t']['opties'], $voor['opties'], 'opties onveranderd' );
klopt( $GLOBALS['t']['plugins'], $voor['plugins'], 'plugins onveranderd' );
klopt( $GLOBALS['t']['cron'], $voor['cron'], 'cron onveranderd' );
klopt( $GLOBALS['t']['log'], array(), 'niets gedeactiveerd, verwijderd of gemaild' );
klopt( count( $p['regels'] ) > 5, true, 'rapport met regels' );

rm_map( WP_CONTENT_DIR );
echo $fouten ? "\n$fouten fout(en)\n" : "\nAlles goed\n";
exit( $fouten ? 1 : 0 );
