<?php
/**
 * Mediamora Toolkit, testgevallen voor Afbeeldingstitels verbergen.
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/afbeeldingstitels.php
 * Geeft exitcode 1 als er een geval misgaat.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );

// Het modulebestand roept bij het laden alleen add_filter aan.
$filters = array();
function add_filter( $hook, $callback ) {
	$GLOBALS['filters'][] = array( $hook, $callback );
}

require dirname( __DIR__ ) . '/modules/afbeeldingstitels.php';

$gevallen = array(
	// Zoals Elementor 4.4 de <img> opbouwt bij een eigen uitsnede.
	array(
		'<img src="https://drentz.nl/wp-content/uploads/elementor/thumbs/FleurFotografeert-Drentz-27-r1x2.jpg" title="FleurFotografeert-Drentz-27" alt="Twee mensen aan tafel" loading="lazy" />',
		'<img src="https://drentz.nl/wp-content/uploads/elementor/thumbs/FleurFotografeert-Drentz-27-r1x2.jpg" alt="Twee mensen aan tafel" loading="lazy" />',
	),
	// Met hover-animatie krijgt hij een class achter de alt.
	array(
		'<img src="a.jpg" title="Titel" alt="" class="elementor-animation-grow" loading="lazy" />',
		'<img src="a.jpg" alt="" class="elementor-animation-grow" loading="lazy" />',
	),
	// Lege titel.
	array(
		'<img src="a.jpg" title="" alt="Alt" loading="lazy" />',
		'<img src="a.jpg" alt="Alt" loading="lazy" />',
	),
	// Hoofdletters en een titel met escapete tekens.
	array(
		'<IMG src="a.jpg" TITLE="Fleur &amp; Drentz &quot;27&quot;" alt="x">',
		'<IMG src="a.jpg" alt="x">',
	),
	// Na Afbeeldingen: ruimte en voorrang, met width, height en data-no-lazy ervoor.
	array(
		'<img data-no-lazy="1" class="skip-lazy" loading="eager" width="1000" height="1333" src="a.jpg" title="Bestand" alt="Alt" />',
		'<img data-no-lazy="1" class="skip-lazy" loading="eager" width="1000" height="1333" src="a.jpg" alt="Alt" />',
	),
	// Meer afbeeldingen in één stuk HTML, zoals bij een flip box.
	array(
		'<div><img src="a.jpg" title="Een" alt="1"><img src="b.jpg" title="Twee" alt="2"></div>',
		'<div><img src="a.jpg" alt="1"><img src="b.jpg" alt="2"></div>',
	),

	// Moeten blijven zoals ze zijn.
	array(
		'<img width="800" height="600" src="a.jpg" class="attachment-large size-large wp-image-12" alt="Alt" />',
		'<img width="800" height="600" src="a.jpg" class="attachment-large size-large wp-image-12" alt="Alt" />',
	),
	array(
		'<img src="a.jpg" data-title="Blijft" alt="Alt">',
		'<img src="a.jpg" data-title="Blijft" alt="Alt">',
	),
	array(
		'<a href="/over" title="Over DRENTZ."><img src="a.jpg" alt="Alt"></a>',
		'<a href="/over" title="Over DRENTZ."><img src="a.jpg" alt="Alt"></a>',
	),
	array(
		'<img src="a.jpg" alt="Een title=&quot;x&quot; in de alt">',
		'<img src="a.jpg" alt="Een title=&quot;x&quot; in de alt">',
	),
	array( '', '' ),
);

$fout = 0;
foreach ( $gevallen as $geval ) {
	list( $invoer, $verwacht ) = $geval;
	$uitkomst = mm_verberg_afbeeldingstitel( $invoer );
	if ( $uitkomst !== $verwacht ) {
		$fout++;
		printf( "FOUT  %s\n      gaf      %s\n      verwacht %s\n", $invoer, var_export( $uitkomst, true ), $verwacht );
	}
}

// Geen string: ongemoeid terug.
if ( null !== mm_verberg_afbeeldingstitel( null ) ) {
	$fout++;
	echo "FOUT  null kwam niet ongemoeid terug\n";
}

// Aan het goede filter.
if ( array( array( 'elementor/image_size/get_attachment_image_html', 'mm_verberg_afbeeldingstitel' ) ) !== $filters ) {
	$fout++;
	echo "FOUT  filter niet (alleen) aan elementor/image_size/get_attachment_image_html\n";
}

$totaal = count( $gevallen ) + 2;
printf( "%d van %d gevallen goed\n", $totaal - $fout, $totaal );
exit( $fout ? 1 : 0 );
