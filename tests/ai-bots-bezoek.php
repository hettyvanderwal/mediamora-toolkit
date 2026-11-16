<?php
/**
 * Mediamora Toolkit, testgevallen voor "Bezoekers vanuit AI-antwoorden"
 * in de module AI-bots: welke verwijzer of utm_source bij welke bron hoort,
 * en welke meldingen uit de browser wel en niet geteld worden.
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/ai-bots-bezoek.php
 * Geeft exitcode 1 als er een geval misgaat.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );

// Het modulebestand roept bij het laden alleen add_action aan.
function add_action() {}

$GLOBALS['opties'] = array();

function get_option( $naam, $standaard = false ) {
	return array_key_exists( $naam, $GLOBALS['opties'] ) ? $GLOBALS['opties'][ $naam ] : $standaard;
}
function update_option( $naam, $waarde ) {
	$GLOBALS['opties'][ $naam ] = $waarde;
	return true;
}
function wp_unslash( $waarde ) {
	return is_string( $waarde ) ? stripslashes( $waarde ) : $waarde;
}
function wp_parse_url( $url, $deel = -1 ) {
	return parse_url( $url, $deel );
}
function sanitize_text_field( $waarde ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $waarde ) ) );
}
function home_url( $pad = '' ) {
	return 'https://falcon-i.nl' . $pad;
}

require dirname( __DIR__ ) . '/modules/ai-bots.php';

$fouten = 0;

function verwacht( $omschrijving, $gekregen, $verwacht ) {
	global $fouten;
	if ( $gekregen === $verwacht ) {
		echo "ok   $omschrijving\n";
		return;
	}
	$fouten++;
	echo "FOUT $omschrijving: verwacht " . var_export( $verwacht, true ) . ', kreeg ' . var_export( $gekregen, true ) . "\n";
}

/* Herkenning van de bron */

verwacht( 'chatgpt.com als verwijzer', mm_aibots_bron( 'chatgpt.com', '' ), 'ChatGPT' );
verwacht( 'www. en hoofdletters', mm_aibots_bron( 'WWW.Perplexity.AI', '' ), 'Perplexity' );
verwacht( 'subdomein telt mee', mm_aibots_bron( 'labs.perplexity.ai', '' ), 'Perplexity' );
verwacht( 'gemini.google.com', mm_aibots_bron( 'gemini.google.com', '' ), 'Gemini' );
verwacht( 'gewoon google.com is geen AI-bron', mm_aibots_bron( 'google.com', '' ), '' );
verwacht( 'www.bing.com is geen AI-bron', mm_aibots_bron( 'www.bing.com', '' ), '' );
verwacht( 'nepdomein met de naam erin telt niet', mm_aibots_bron( 'chatgpt.com.voorbeeld.nl', '' ), '' );
verwacht( 'domein dat op de naam eindigt telt niet', mm_aibots_bron( 'notchatgpt.com', '' ), '' );
verwacht( 'utm_source=chatgpt.com', mm_aibots_bron( '', 'chatgpt.com' ), 'ChatGPT' );
verwacht( 'utm_source=perplexity', mm_aibots_bron( '', 'perplexity' ), 'Perplexity' );
verwacht( 'utm_source=copilot.com', mm_aibots_bron( '', 'copilot.com' ), 'Copilot' );
verwacht( 'utm_source=facebook telt niet', mm_aibots_bron( '', 'facebook' ), '' );
verwacht( 'utm_source=meta (Meta-advertenties) telt niet', mm_aibots_bron( '', 'meta' ), '' );
verwacht( 'utm_source=newsletter telt niet', mm_aibots_bron( '', 'newsletter' ), '' );
verwacht( 'verwijzer gaat voor utm', mm_aibots_bron( 'claude.ai', 'chatgpt.com' ), 'Claude' );
verwacht( 'onbekende verwijzer, wel AI in utm', mm_aibots_bron( 'l.facebook.com', 'chatgpt.com' ), 'ChatGPT' );

/* Meldingen uit de browser */

$browser = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/131 Safari/537.36';
$maand   = 'mm_aibots_bezoek_' . gmdate( 'Y-m' );

verwacht(
	'melding vanuit ChatGPT telt',
	mm_aibots_bezoek_verwerk( array( 'h' => 'chatgpt.com', 'u' => 'chatgpt.com', 'p' => '/inspecties-en-inventarisaties/groenbeheer/' ), $browser, 'https://falcon-i.nl' ),
	'ChatGPT'
);
verwacht(
	'tweede melding op dezelfde pagina hoogt op',
	mm_aibots_bezoek_verwerk( array( 'h' => '', 'u' => 'chatgpt.com', 'p' => '/inspecties-en-inventarisaties/groenbeheer/' ), $browser, '' ),
	'ChatGPT'
);
verwacht( 'teller staat op 2', $GLOBALS['opties'][ $maand ]['ChatGPT']['/inspecties-en-inventarisaties/groenbeheer/'] ?? null, 2 );

verwacht(
	'www-variant van het eigen domein als Origin telt',
	mm_aibots_bezoek_verwerk( array( 'h' => 'perplexity.ai', 'u' => '', 'p' => '/' ), $browser, 'https://www.falcon-i.nl' ),
	'Perplexity'
);
verwacht(
	'Origin van een ander domein telt niet',
	mm_aibots_bezoek_verwerk( array( 'h' => 'chatgpt.com', 'u' => '', 'p' => '/' ), $browser, 'https://voorbeeld.nl' ),
	''
);
verwacht(
	'crawler als user agent telt niet',
	mm_aibots_bezoek_verwerk( array( 'h' => 'chatgpt.com', 'u' => '', 'p' => '/' ), 'Mozilla/5.0 (compatible; ChatGPT-User/1.0; +https://openai.com/bot)', '' ),
	''
);
verwacht(
	'onbekende bron telt niet',
	mm_aibots_bezoek_verwerk( array( 'h' => 'google.com', 'u' => '', 'p' => '/' ), $browser, '' ),
	''
);
verwacht(
	'statisch bestand telt niet',
	mm_aibots_bezoek_verwerk( array( 'h' => 'chatgpt.com', 'u' => '', 'p' => '/wp-content/uploads/foto.jpg' ), $browser, '' ),
	''
);
verwacht(
	'querystring en host worden uit het pad gehaald',
	mm_aibots_bezoek_verwerk( array( 'h' => 'claude.ai', 'u' => '', 'p' => '//voorbeeld.nl/contact/?x=1' ), $browser, '' ),
	'Claude'
);
verwacht( 'pad zonder querystring opgeslagen', array_keys( $GLOBALS['opties'][ $maand ]['Claude'] ?? array() ), array( '//voorbeeld.nl/contact/' ) );
verwacht(
	'HTML in de velden telt niet als bron',
	mm_aibots_bezoek_verwerk( array( 'h' => '<b>chatgpt.com</b>x', 'u' => '', 'p' => '/' ), $browser, '' ),
	''
);
verwacht( 'alleen bekende bronnen in het log', array_keys( $GLOBALS['opties'][ $maand ] ), array( 'ChatGPT', 'Perplexity', 'Claude' ) );

/* Padenlimiet */

$GLOBALS['opties'][ $maand ] = array();
for ( $i = 0; $i < MM_AIBOTS_MAX_PADEN + 5; $i++ ) {
	mm_aibots_bezoek_verwerk( array( 'h' => 'gemini.google.com', 'u' => '', 'p' => '/pagina-' . $i . '/' ), $browser, '' );
}
verwacht( 'niet meer paden dan de limiet plus overig', count( $GLOBALS['opties'][ $maand ]['Gemini'] ), MM_AIBOTS_MAX_PADEN + 1 );
verwacht( 'de rest staat onder overig', $GLOBALS['opties'][ $maand ]['Gemini']['(overig)'] ?? null, 5 );

echo $fouten ? "\n$fouten fout(en)\n" : "\nAlles goed\n";
exit( $fouten ? 1 : 0 );
