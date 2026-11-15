<?php
/**
 * Mediamora Toolkit, testgevallen voor de rapportmail en de vangnet-mail
 * van de anti-spam: welke logregels komen in welke mail.
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/anti-spam-rapport.php
 * Geeft exitcode 1 als er een geval misgaat.
 *
 * De instellingen van de module worden per proces één keer gelezen. Het
 * deel over uit- en weer aanzetten draait daarom in een eigen proces,
 * met beide mails uit als beginstand.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$deel = $argv[1] ?? 'mails';

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/mm-antispam-test-' . getmypid() );

const LOGMAP = 'mediamora-antispam-testtest1234';

@mkdir( WP_CONTENT_DIR . '/uploads/' . LOGMAP, 0777, true );

register_shutdown_function(
	function () {
		array_map( 'unlink', glob( WP_CONTENT_DIR . '/uploads/' . LOGMAP . '/*' ) );
		@rmdir( WP_CONTENT_DIR . '/uploads/' . LOGMAP );
		@rmdir( WP_CONTENT_DIR . '/uploads' );
		@rmdir( WP_CONTENT_DIR );
	}
);

// Het modulebestand roept bij het laden alleen add_action en add_filter aan.
function add_action() {}
function add_filter() {}

$GLOBALS['opties'] = array( 'mediamora_antispam_log_dir' => LOGMAP );
$GLOBALS['mails']  = array();
$GLOBALS['events'] = array(); // hook => tijdstip van het geplande cron-event
$GLOBALS['mail_lukt'] = true;

if ( 'aanzetten' === $deel ) {
	$GLOBALS['opties']['mediamora_antispam_settings'] = array(
		'report_enabled'         => false,
		'nearmiss_alert_enabled' => false,
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
function wp_mail( $aan, $onderwerp, $tekst ) {
	if ( ! $GLOBALS['mail_lukt'] ) {
		return false;
	}
	$GLOBALS['mails'][] = array( 'onderwerp' => $onderwerp, 'tekst' => $tekst );
	return true;
}
function wp_next_scheduled( $hook ) {
	return $GLOBALS['events'][ $hook ] ?? false;
}
function wp_schedule_single_event( $tijd, $hook ) {
	$GLOBALS['events'][ $hook ] = $tijd;
	return true;
}
function home_url() {
	return 'https://voorbeeld.nl';
}
function wp_parse_url( $url, $deel ) {
	return parse_url( $url, $deel );
}
function sanitize_email( $email ) {
	return $email;
}
function is_email( $email ) {
	return false !== strpos( $email, '@' );
}

require dirname( __DIR__ ) . '/modules/anti-spam.php';

$fout = 0;
$test = function ( $naam, $goed ) use ( &$fout ) {
	echo ( $goed ? 'goed ' : 'FOUT ' ), $naam, "\n";
	$fout += $goed ? 0 : 1;
};

// Een logregel zoals mediamora_antispam_log() en _nearmiss_log() die schrijven.
function regel( $tijd, $label ) {
	return gmdate( 'Y-m-d H:i:s', $tijd ) . ' | veld: bericht (textarea) | reden: test | waarde: ' . $label;
}

function schrijf_log( $bestand, $regels ) {
	file_put_contents( $bestand, implode( "\n", $regels ) . "\n" );
}

// De labels van de regels in de laatst verstuurde mail, in volgorde.
function labels_in_mail() {
	$mail = end( $GLOBALS['mails'] );
	if ( ! $mail || ! preg_match_all( '/\| waarde: (\S+)$/m', $mail['tekst'], $m ) ) {
		return array();
	}
	return $m[1];
}

function labels_in_bestand( $bestand ) {
	preg_match_all( '/\| waarde: (\S+)$/m', (string) @file_get_contents( $bestand ), $m );
	return $m[1];
}

// Doet alsof er $seconden verstreken zijn: alle logtijden en de tijdstippen
// van de vorige mails schuiven evenveel naar achteren.
function verschuif( $bestand, $seconden ) {
	$regels = file( $bestand, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	foreach ( $regels as $i => $r ) {
		$tijd       = mediamora_antispam_parse_line_timestamp( $r );
		$regels[ $i ] = gmdate( 'Y-m-d H:i:s', $tijd - $seconden ) . substr( $r, 19 );
	}
	schrijf_log( $bestand, $regels );
	foreach ( array( 'mediamora_antispam_last_report', 'mediamora_antispam_last_nearmiss_alert' ) as $optie ) {
		if ( ! empty( $GLOBALS['opties'][ $optie ] ) ) {
			$GLOBALS['opties'][ $optie ] -= $seconden;
		}
	}
}

// Wacht tot het begin van een nieuwe seconde, zodat wat hierna gebeurt
// zeker binnen dezelfde seconde valt.
function nieuwe_seconde() {
	$nu = time();
	while ( time() === $nu ) {
		usleep( 2000 );
	}
}

const DAG = DAY_IN_SECONDS;

if ( 'aanzetten' === $deel ) {

	// Rapportage uit: de run ruimt op en zet last_report bij, maar mailt niet.
	$log = mediamora_antispam_log_file();
	$nu  = time();
	schrijf_log( $log, array( regel( $nu - 2 * DAG, 'uit1' ), regel( $nu - 3600, 'uit2' ) ) );
	$GLOBALS['opties']['mediamora_antispam_last_report'] = $nu - 3 * DAG;
	mediamora_antispam_maybe_send_report();
	$test( 'rapport uit: geen mail', array() === $GLOBALS['mails'] );
	$test( 'rapport uit: last_report wel bijgewerkt', get_option( 'mediamora_antispam_last_report' ) >= $nu );

	// Vangnet uit: de functie stopt meteen, last_nearmiss_alert blijft oud.
	$GLOBALS['opties']['mediamora_antispam_last_nearmiss_alert'] = $nu - 10 * DAG;
	mediamora_antispam_maybe_send_nearmiss_alert();
	$test( 'vangnet uit: last_nearmiss_alert blijft staan', get_option( 'mediamora_antispam_last_nearmiss_alert' ) === $nu - 10 * DAG );

	// Weer aanzetten: beide beginnen bij nu, er wordt niets ingehaald.
	$GLOBALS['opties']['mediamora_antispam_last_report'] = $nu - 3 * DAG;
	$voor = time();
	mediamora_antispam_sanitize_and_save_settings(
		array(
			'alert_email'            => 'test@voorbeeld.nl',
			'report_enabled'         => '1',
			'nearmiss_alert_enabled' => '1',
		)
	);
	$test( 'rapport weer aan: last_report op nu', get_option( 'mediamora_antispam_last_report' ) >= $voor );
	$test( 'vangnet weer aan: last_nearmiss_alert op nu', get_option( 'mediamora_antispam_last_nearmiss_alert' ) >= $voor );

	exit( $fout ? 1 : 0 );
}

// Loopt dezelfde gevallen door voor de rapportmail en voor de vangnet-mail.
$soorten = array(
	'rapport' => array(
		'bestand' => mediamora_antispam_log_file(),
		'optie'   => 'mediamora_antispam_last_report',
		'stuur'   => 'mediamora_antispam_maybe_send_report',
		'hook'    => 'mediamora_antispam_report',
		'log'     => function () {
			mediamora_antispam_log( 'test', 'bericht', 'textarea', 'nieuw' );
		},
	),
	'vangnet' => array(
		'bestand' => mediamora_antispam_nearmiss_file(),
		'optie'   => 'mediamora_antispam_last_nearmiss_alert',
		'stuur'   => 'mediamora_antispam_maybe_send_nearmiss_alert',
		'hook'    => 'mediamora_antispam_nearmiss_alert',
		'log'     => function () {
			mediamora_antispam_nearmiss_log( 'bericht', 'textarea', 'nieuw', array( 'test' ), 'doorgelaten' );
		},
	),
);

foreach ( $soorten as $soort => $s ) {

	$bestand = $s['bestand'];
	$optie   = $s['optie'];
	$stuur   = $s['stuur'];
	$hook    = $s['hook'];
	$logt    = $s['log'];

	// 1. Allereerste mail (optie leeg): alleen de laatste 24 uur, zoals eerst.
	$GLOBALS['mails'] = array();
	unset( $GLOBALS['opties'][ $optie ] );
	$nu = time();
	schrijf_log( $bestand, array( regel( $nu - 2 * DAG, 'eerst_oud' ), regel( $nu - 23 * 3600, 'eerst_nieuw' ) ) );
	$stuur();
	$test( "$soort, eerste keer: alleen de laatste 24 uur", array( 'eerst_nieuw' ) === labels_in_mail() );

	// 2. Vorige mail ruim meer dan 24 uur geleden: alles sinds die mail.
	$GLOBALS['mails'] = array();
	$nu               = time();
	$vorige           = $nu - 4 * DAG;
	$GLOBALS['opties'][ $optie ] = $vorige;
	schrijf_log(
		$bestand,
		array(
			regel( $nu - 40 * DAG, 'verlopen' ),       // buiten de bewaartermijn van 30 dagen
			regel( $nu - 6 * DAG, 'al_gemaild' ),
			regel( $vorige - 1, 'net_voor_vorige' ),   // zat in de vorige mail
			regel( $vorige, 'op_vorige' ),             // in de seconde van de vorige mail, zat er nog niet in
			regel( $nu - 3 * DAG - 7200, 'r1' ),
			regel( $nu - 2 * DAG, 'r2' ),
			regel( $nu - DAG - 3600, 'r3' ),           // viel eerst weg: ouder dan 24 uur
			regel( $nu - 3600, 'r4' ),
		)
	);
	$stuur();
	$test( "$soort: één mail", 1 === count( $GLOBALS['mails'] ) );
	$test( "$soort: alle regels sinds de vorige mail, niets ervoor", array( 'op_vorige', 'r1', 'r2', 'r3', 'r4' ) === labels_in_mail() );
	$test( "$soort: aantal in het onderwerp", false !== strpos( $GLOBALS['mails'][0]['onderwerp'], ': 5 ' ) );
	$test( "$soort: bewaartermijn opgeschoond, de rest blijft", array( 'al_gemaild', 'net_voor_vorige', 'op_vorige', 'r1', 'r2', 'r3', 'r4' ) === labels_in_bestand( $bestand ) );

	// 3. Meteen nog eens: de periode is niet om, geen mail.
	$stuur();
	$test( "$soort: binnen de periode geen tweede mail", 1 === count( $GLOBALS['mails'] ) );

	// 4. Een regel in precies de seconde van het verzenden komt pas in de
	// volgende mail, en dan één keer.
	nieuwe_seconde();
	$moment = time();
	file_put_contents( $bestand, regel( $moment, 'zelfde_seconde' ) . "\n", FILE_APPEND );
	$GLOBALS['opties'][ $optie ] = $moment - 2 * DAG;
	$GLOBALS['mails'] = array();
	$stuur();
	$gelukt = get_option( $optie ) === $moment;
	if ( $gelukt ) {
		$test( "$soort: regel uit de verzendseconde nog niet in deze mail", ! in_array( 'zelfde_seconde', labels_in_mail(), true ) );
	} else {
		echo "let op: $soort, de seconde tikte door tijdens het verzenden, grensgeval niet getest\n";
	}

	// 5. Twee dagen later, met nieuwe regels: alleen wat sinds de vorige
	// mail bijkwam, geen enkele regel dubbel.
	verschuif( $bestand, 2 * DAG );
	$nu = time();
	file_put_contents( $bestand, regel( $nu - DAG - 600, 'r5' ) . "\n" . regel( $nu - 60, 'r6' ) . "\n", FILE_APPEND );
	$GLOBALS['mails'] = array();
	$stuur();
	$verwacht = $gelukt ? array( 'zelfde_seconde', 'r5', 'r6' ) : array( 'r5', 'r6' );
	$test( "$soort: volgende mail alleen met de nieuwe regels", $verwacht === labels_in_mail() );

	// 6. Vorige mail van voor de bewaartermijn: regels die al uit het log
	// horen, komen ook niet in de mail.
	$GLOBALS['mails'] = array();
	$nu               = time();
	$GLOBALS['opties'][ $optie ] = $nu - 40 * DAG;
	schrijf_log( $bestand, array( regel( $nu - 35 * DAG, 'te_oud' ), regel( $nu - 10 * DAG, 'r7' ) ) );
	$stuur();
	$test( "$soort: niets van buiten de bewaartermijn", array( 'r7' ) === labels_in_mail() );

	// 7. Inplannen. Een regel vlak na een mail: het event komt op het eind
	// van de periode, niet pas bij de volgende weigering.
	$GLOBALS['events'] = array();
	$nu                = time();
	$GLOBALS['opties'][ $optie ] = $nu - 3600;
	$logt();
	$test( "$soort: regel binnen de periode ingepland op het eind van de periode", ( $GLOBALS['events'][ $hook ] ?? 0 ) === $nu - 3600 + DAG );
	$logt();
	$test( "$soort: tweede regel plant niets extra", ( $GLOBALS['events'][ $hook ] ?? 0 ) === $nu - 3600 + DAG );
	$GLOBALS['events'] = array();
	$GLOBALS['opties'][ $optie ] = $nu - 2 * DAG;
	$logt();
	$test( "$soort: regel na de periode meteen ingepland", ( $GLOBALS['events'][ $hook ] ?? PHP_INT_MAX ) <= time() );

	// 8. Event dat te vroeg draait: plant zich opnieuw in op het eind van
	// de periode en mailt niets.
	$GLOBALS['events'] = array();
	$GLOBALS['mails']  = array();
	$GLOBALS['opties'][ $optie ] = $nu - 3600;
	$stuur();
	$test( "$soort: te vroeg, geen mail", array() === $GLOBALS['mails'] );
	$test( "$soort: te vroeg, opnieuw ingepland op het eind van de periode", ( $GLOBALS['events'][ $hook ] ?? 0 ) === $nu - 3600 + DAG );

	// 9. Mislukte mail: het tijdstip blijft staan, er komt een nieuwe poging
	// over een periode, en die neemt dezelfde regels mee plus wat erbij kwam.
	$GLOBALS['events']   = array();
	$GLOBALS['mails']    = array();
	$GLOBALS['mail_lukt'] = false;
	$nu                  = time();
	$vorige              = $nu - 2 * DAG;
	$GLOBALS['opties'][ $optie ] = $vorige;
	schrijf_log( $bestand, array( regel( $nu - DAG - 60, 'm1' ), regel( $nu - 600, 'm2' ) ) );
	$stuur();
	$test( "$soort, mislukte mail: tijdstip vorige mail blijft staan", get_option( $optie ) === $vorige );
	$test( "$soort, mislukte mail: nieuwe poging over een periode", ( $GLOBALS['events'][ $hook ] ?? 0 ) >= $nu + DAG && $GLOBALS['events'][ $hook ] <= time() + DAG );
	$test( "$soort, mislukte mail: regels blijven in het log", array( 'm1', 'm2' ) === labels_in_bestand( $bestand ) );
	$GLOBALS['mail_lukt'] = true;
	$GLOBALS['events']    = array();
	verschuif( $bestand, DAG );
	$nu = time();
	file_put_contents( $bestand, regel( $nu - 60, 'm3' ) . "\n", FILE_APPEND );
	$stuur();
	$test( "$soort, nieuwe poging: dezelfde regels plus de nieuwe, niets dubbel", array( 'm1', 'm2', 'm3' ) === labels_in_mail() && 1 === count( $GLOBALS['mails'] ) );
	$test( "$soort, nieuwe poging gelukt: tijdstip bijgewerkt", get_option( $optie ) >= $nu );
}

// Opslaan terwijl beide al aan staan: de tijdstippen blijven staan.
$GLOBALS['opties']['mediamora_antispam_last_report']         = 12345;
$GLOBALS['opties']['mediamora_antispam_last_nearmiss_alert'] = 67890;
mediamora_antispam_sanitize_and_save_settings(
	array(
		'alert_email'            => 'test@voorbeeld.nl',
		'report_enabled'         => '1',
		'nearmiss_alert_enabled' => '1',
	)
);
$test( 'opslaan met alles al aan: tijdstippen blijven staan', 12345 === get_option( 'mediamora_antispam_last_report' ) && 67890 === get_option( 'mediamora_antispam_last_nearmiss_alert' ) );

// Het deel over uit- en aanzetten in een eigen proces.
passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' aanzetten', $code );
$fout += $code ? 1 : 0;

echo $fout ? "$fout fout(en)\n" : "alles goed\n";
exit( $fout ? 1 : 0 );
