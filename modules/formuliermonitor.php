<?php
/**
 * Mediamora Toolkit, module: Formuliermonitor
 * Overgenomen uit mediamora-formuliermonitor 1.0.1. Wordt alleen geladen als de module aanstaat.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MM_MONITOR_OPTIE_TELLER = 'mm_monitor_teller';
const MM_MONITOR_OPTIE_LOG    = 'mm_monitor_log';
const MM_MONITOR_LOG_MAX      = 20;

/**
 * Ontvanger van de meldingen.
 * Te overschrijven per site via wp-config.php.
 */
if ( ! defined( 'MM_MONITOR_ONTVANGER' ) ) {
	define( 'MM_MONITOR_ONTVANGER', 'hetty@mediamora.nl' );
}

/**
 * Maximaal aantal meldingen per site per dag.
 * Op 1 houden, anders zet een spambot de mailbox vol.
 */
if ( ! defined( 'MM_MONITOR_MAX_PER_DAG' ) ) {
	define( 'MM_MONITOR_MAX_PER_DAG', 1 );
}


/* -------------------------------------------------------------------------
 * Bewaking
 * ---------------------------------------------------------------------- */

/**
 * WordPress vuurt deze actie af zodra een uitgaande mail niet verstuurd kon
 * worden. Elementor, WooCommerce, wachtwoordherstel en elke plugin die
 * wp_mail() gebruikt komen hier langs.
 */
add_action( 'wp_mail_failed', 'mm_monitor_verwerk_fout', 10, 1 );

/**
 * @param WP_Error $fout
 */
function mm_monitor_verwerk_fout( $fout ) {

	if ( ! is_wp_error( $fout ) ) {
		return;
	}

	$gegevens  = $fout->get_error_data();
	$onderwerp = '';

	if ( is_array( $gegevens ) && ! empty( $gegevens['subject'] ) ) {
		$onderwerp = (string) $gegevens['subject'];
	}

	$regel = array(
		'tijd'      => current_time( 'Y-m-d H:i:s' ),
		'melding'   => $fout->get_error_message(),
		'code'      => $fout->get_error_code(),
		'onderwerp' => $onderwerp,
	);

	mm_monitor_schrijf_log( $regel );

	$vandaag = current_time( 'Y-m-d' );
	$teller  = get_option( MM_MONITOR_OPTIE_TELLER, array() );

	if ( ! is_array( $teller ) || ! isset( $teller['datum'] ) || $teller['datum'] !== $vandaag ) {
		$teller = array(
			'datum'     => $vandaag,
			'fouten'    => 0,
			'verstuurd' => 0,
		);
	}

	$teller['fouten']++;

	if ( $teller['verstuurd'] < MM_MONITOR_MAX_PER_DAG ) {
		if ( mm_monitor_verstuur_melding( $regel, $teller['fouten'] ) ) {
			$teller['verstuurd']++;
		}
	}

	update_option( MM_MONITOR_OPTIE_TELLER, $teller, false );
}

/**
 * Verstuurt de waarschuwing met de kale PHP-mailfunctie.
 *
 * Bewust NIET met wp_mail(): die route is op dit moment juist de kapotte.
 * Door mail() rechtstreeks aan te roepen wordt elke SMTP-plugin,
 * OAuth-koppeling en phpmailer-filter overgeslagen en gaat het bericht
 * langs de mailserver van de hosting zelf de deur uit.
 *
 * Bijkomend voordeel: mislukt deze melding zelf, dan vuurt wp_mail_failed
 * niet opnieuw af en kan er dus geen lus ontstaan.
 *
 * @param array $regel
 * @param int   $aantal_vandaag
 * @return bool
 */
function mm_monitor_verstuur_melding( $regel, $aantal_vandaag ) {

	if ( ! function_exists( 'mail' ) ) {
		return false;
	}

	$domein   = mm_monitor_domein();
	$afzender = 'no-reply@' . $domein;

	$onderwerp = sprintf( '[Formuliermonitor] %s', $domein );

	$body   = array();
	$body[] = sprintf( 'Site:     %s', $domein );
	$body[] = sprintf( 'Tijdstip: %s (%s)', $regel['tijd'], wp_timezone_string() );
	$body[] = sprintf( 'Aantal vandaag: %d', $aantal_vandaag );
	$body[] = '';
	$body[] = 'Melding van de server:';
	$body[] = $regel['melding'];

	if ( '' !== $regel['onderwerp'] ) {
		$body[] = '';
		$body[] = sprintf( 'Betrof: %s', $regel['onderwerp'] );
	}

	$body[] = '';
	$body[] = 'Maximaal een bericht per dag per site.';

	/*
	 * X-Mediamora-Monitor is de vaste kop om in de mailbox op te filteren.
	 * Die verandert nooit, ook niet als het onderwerp ooit anders wordt
	 * geformuleerd. Filter daarop en niet op de tekst van het onderwerp.
	 */
	$headers = array(
		'From: Formuliermonitor ' . $domein . ' <' . $afzender . '>',
		'Reply-To: ' . MM_MONITOR_ONTVANGER,
		'Content-Type: text/plain; charset=UTF-8',
		'X-Mediamora-Monitor: 1',
		'X-Mediamora-Site: ' . $domein,
		'X-Mailer: Mediamora Toolkit ' . MM_TOOLKIT_VERSIE,
	);

	return @mail(
		MM_MONITOR_ONTVANGER,
		$onderwerp,
		implode( "\n", $body ),
		implode( "\r\n", $headers ),
		'-f' . $afzender
	);
}

/**
 * Het domein van deze site, zonder www.
 *
 * @return string
 */
function mm_monitor_domein() {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$host = is_string( $host ) ? $host : 'onbekend';

	return preg_replace( '/^www\./i', '', $host );
}

/**
 * Houdt de laatste fouten lokaal bij, zodat je achteraf kunt terugkijken
 * ook als de melding zelf niet is aangekomen.
 *
 * @param array $regel
 */
function mm_monitor_schrijf_log( $regel ) {
	$log = get_option( MM_MONITOR_OPTIE_LOG, array() );

	if ( ! is_array( $log ) ) {
		$log = array();
	}

	array_unshift( $log, $regel );
	$log = array_slice( $log, 0, MM_MONITOR_LOG_MAX );

	update_option( MM_MONITOR_OPTIE_LOG, $log, false );
}

/**
 * Testknop. Roep als beheerder /wp-admin/?mm_monitor_test=1 aan
 * om te controleren of de melding bij Mediamora aankomt.
 */
add_action( 'admin_init', 'mm_monitor_test' );

function mm_monitor_test() {

	if ( empty( $_GET['mm_monitor_test'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$regel = array(
		'tijd'      => current_time( 'Y-m-d H:i:s' ),
		'melding'   => 'Dit is een testmelding, er is niets kapot.',
		'code'      => 'test',
		'onderwerp' => 'Testmelding vanuit de Formuliermonitor',
	);

	$gelukt = mm_monitor_verstuur_melding( $regel, 0 );

	wp_die(
		$gelukt
			? 'Testmelding verstuurd naar ' . esc_html( MM_MONITOR_ONTVANGER ) . '. Controleer de mailbox.'
			: 'De testmelding kon niet verstuurd worden. Op deze hosting werkt de mailfunctie van de server niet.',
		'Formuliermonitor',
		array( 'response' => 200 )
	);
}


/**
 * Opruimen bij verwijderen van de plugin, bijvoorbeeld als een klant
 * zijn onderhoudspakket opzegt.
 */
function mm_monitor_opruimen() {
	delete_option( MM_MONITOR_OPTIE_TELLER );
	delete_option( MM_MONITOR_OPTIE_LOG );
}
