<?php
/**
 * Mediamora Toolkit, testgevallen voor de linkpatronen van de anti-spam.
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/anti-spam-links.php
 * Geeft exitcode 1 als er een geval misgaat.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );

// Het modulebestand roept bij het laden alleen add_action aan.
function add_action() {}
function add_filter() {}

require dirname( __DIR__ ) . '/modules/anti-spam.php';

$gevallen = array(
	// Moeten door: domeinen die een kort patroon bevatten, maar het niet zijn.
	array( 'mediamora_contains_shortened_url', 'Ik kijk graag netflix.com', false ),
	array( 'mediamora_contains_shortened_url', 'De bestanden staan op dropbox.com', false ),
	array( 'mediamora_contains_shortened_url', 'Mijn adres is jan@gmx.com', false ),
	array( 'mediamora_contains_shortened_url', 'Mail me op gmx.com', false ),
	array( 'mediamora_contains_shortened_url', 'Ik hoor graag van u.Tot ziens', false ),
	array( 'mediamora_contains_shortened_url', 'Groet, info@bit.ly-fan.nl', false ),
	array( 'mediamora_contains_shortened_url', 'Zie ook rebit.lyon.fr', false ),
	array( 'mediamora_contains_scheduling_link', 'Ik werk bij medical.com', false ),
	array( 'mediamora_contains_scheduling_link', 'Mail naar planning@cal.com', false ),
	array( 'mediamora_contains_scheduling_link', 'Mijn site is local.com.au', false ),

	// Moeten blijven hangen: echte verkorte links en boekingslinks.
	array( 'mediamora_contains_shortened_url', 'Kijk hier: https://bit.ly/3abcDEF', true ),
	array( 'mediamora_contains_shortened_url', 'Kijk op bit.ly/3abcDEF', true ),
	array( 'mediamora_contains_shortened_url', 'Link: www.tinyurl.com/xyz', true ),
	array( 'mediamora_contains_shortened_url', 'Ga naar u.to/abc123', true ),
	array( 'mediamora_contains_shortened_url', 'Klik https://is.gd/abc', true ),
	array( 'mediamora_contains_shortened_url', 'Lees telegra.ph/crypto-123', true ),
	array( 'mediamora_contains_shortened_url', 'Zie BIT.LY/ABC.', true ),
	array( 'mediamora_contains_scheduling_link', 'Plan een gesprek via https://cal.com/jan', true ),
	array( 'mediamora_contains_scheduling_link', 'Boek via calendly.com/sales/30min', true ),
);

$fout = 0;
foreach ( $gevallen as $geval ) {
	list( $functie, $tekst, $verwacht ) = $geval;
	$uitkomst = $functie( $tekst );
	if ( $uitkomst !== $verwacht ) {
		$fout++;
		printf( "FOUT  %s(\"%s\"): %s, verwacht %s\n", $functie, $tekst, var_export( $uitkomst, true ), var_export( $verwacht, true ) );
	}
}

printf( "%d van %d gevallen goed\n", count( $gevallen ) - $fout, count( $gevallen ) );
exit( $fout ? 1 : 0 );
