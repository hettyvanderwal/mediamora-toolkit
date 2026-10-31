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

	/*
	 * Het onderwerp van de mislukte mail wordt bewust niet overgenomen: daar
	 * staat vaak een klantnaam of ordernummer in. Van de ontvanger alleen het
	 * domein, niet het volledige adres. Ook in de melding zelf, want PHPMailer
	 * zet er adressen in ("recipients failed: jan@voorbeeld.nl").
	 */
	$gegevens = $fout->get_error_data();

	$regel = array(
		'tijd'      => current_time( 'Y-m-d H:i:s' ),
		'melding'   => mm_monitor_maskeer_adressen( $fout->get_error_message() ),
		'code'      => $fout->get_error_code(),
		'ontvanger' => mm_monitor_ontvanger_domein( is_array( $gegevens ) && isset( $gegevens['to'] ) ? $gegevens['to'] : '' ),
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

	if ( '' !== $regel['ontvanger'] ) {
		$body[] = sprintf( 'Ontvanger: %s', $regel['ontvanger'] );
	}

	$body[] = '';
	$body[] = 'Melding van de server:';
	$body[] = $regel['melding'];
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
 * Alleen de domeinen van de ontvanger(s), zonder naam of adres ervoor.
 * wp_mail geeft 'to' door als lijst, maar een losse string met komma's
 * kan ook, net als de vorm "Naam <adres@domein.nl>".
 *
 * @param string|array $to
 * @return string Bijvoorbeeld "@gmail.com, @mediamora.nl", of leeg.
 */
function mm_monitor_ontvanger_domein( $to ) {
	$adressen = is_array( $to ) ? $to : explode( ',', (string) $to );
	$domeinen = array();

	foreach ( $adressen as $adres ) {
		if ( preg_match( '/@([^\s>@]+)\s*>?\s*$/', trim( (string) $adres ), $m ) ) {
			$domeinen[] = '@' . strtolower( $m[1] );
		}
	}

	return implode( ', ', array_unique( $domeinen ) );
}

/**
 * Vervangt in een tekst het deel van elk e-mailadres voor de @ door ***,
 * zodat alleen het domein overblijft.
 *
 * @param string $tekst
 * @return string
 */
function mm_monitor_maskeer_adressen( $tekst ) {
	return (string) preg_replace( '/[a-z0-9._%+\'-]+@([a-z0-9-]+(?:\.[a-z0-9-]+)*\.[a-z]{2,})/i', '***@$1', (string) $tekst );
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

	// Oudere regels bevatten nog het onderwerp van de mail of volledige
	// adressen in de melding. Dat gaat er bij de eerstvolgende fout alsnog uit.
	foreach ( $log as $i => $oud ) {
		if ( is_array( $oud ) ) {
			unset( $log[ $i ]['onderwerp'] );
			if ( isset( $oud['melding'] ) ) {
				$log[ $i ]['melding'] = mm_monitor_maskeer_adressen( $oud['melding'] );
			}
		}
	}

	array_unshift( $log, $regel );
	$log = array_slice( $log, 0, MM_MONITOR_LOG_MAX );

	update_option( MM_MONITOR_OPTIE_LOG, $log, false );
}

/**
 * Testknop: stuurt een testmelding om te controleren of die bij Mediamora
 * aankomt. De link staat bij de Formuliermonitor onder Instellingen >
 * Mediamora Toolkit en draagt een nonce, zodat een link van buitenaf geen
 * mail kan laten versturen namens een ingelogde beheerder.
 */
function mm_monitor_test_url() {
	return wp_nonce_url( add_query_arg( 'mm_monitor_test', '1', admin_url( '/' ) ), 'mm_monitor_test' );
}

add_action( 'admin_init', 'mm_monitor_test' );

function mm_monitor_test() {

	if ( empty( $_GET['mm_monitor_test'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Zonder geldige nonce niets versturen. Een oud adres zonder nonce, of een
	// verlopen link, krijgt een knop met een verse link in plaats van een mail.
	if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'mm_monitor_test' ) ) {
		wp_die(
			'<p>Deze link om een testmelding te sturen is niet (meer) geldig.</p>'
				. '<p><a class="button button-primary" href="' . esc_url( mm_monitor_test_url() ) . '">Testmelding sturen</a></p>',
			'Formuliermonitor',
			array( 'response' => 403 )
		);
	}

	$regel = array(
		'tijd'      => current_time( 'Y-m-d H:i:s' ),
		'melding'   => 'Dit is een testmelding, er is niets kapot.',
		'code'      => 'test',
		'ontvanger' => mm_monitor_ontvanger_domein( MM_MONITOR_ONTVANGER ),
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
