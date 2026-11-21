<?php
/**
 * Mediamora Toolkit, testgevallen voor E-mailadressen afschermen.
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/email-afschermen.php
 *   php tests/email-afschermen.php pagina.html   (schermt een opgeslagen
 *                                                 pagina af naar stdout)
 * Geeft exitcode 1 als er een geval misgaat.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['hooks'] = array();
function add_action( $hook, $callback, $prioriteit = 10 ) {
	$GLOBALS['hooks'][ $hook ][] = $callback;
}
function add_filter( $hook, $callback, $prioriteit = 10 ) {
	add_action( $hook, $callback, $prioriteit );
}

require dirname( __DIR__ ) . '/modules/email-afschermen.php';

if ( isset( $argv[1] ) ) {
	echo mm_email_afschermen_html( file_get_contents( $argv[1] ) );
	exit( 0 );
}

/** Wat het script in de browser doet, in PHP. */
function decodeer( $hex ) {
	$sleutel = hexdec( substr( $hex, 0, 2 ) );
	$uit     = '';
	for ( $i = 2; $i < strlen( $hex ); $i += 2 ) {
		$uit .= chr( hexdec( substr( $hex, $i, 2 ) ) ^ $sleutel );
	}
	return $uit;
}

function terugzetten( $html ) {
	$html = preg_replace_callback(
		'~<span data-mm-t="([0-9a-f]+)">.*?</span>~',
		function ( $m ) {
			return htmlspecialchars( decodeer( $m[1] ), ENT_QUOTES, 'UTF-8' );
		},
		$html
	);
	return preg_replace_callback(
		'~<[a-z][^>]*\sdata-mm-e="([^"]+)"[^>]*>~i',
		function ( $m ) {
			$tag = str_replace( ' data-mm-e="' . $m[1] . '"', '', $m[0] );
			foreach ( explode( ',', $m[1] ) as $paar ) {
				list( $naam, $hex ) = explode( ':', $paar );
				$tag = preg_replace( '~(\s' . preg_quote( $naam, '~' ) . ')="[^"]*"~i', '$1="' . htmlspecialchars( decodeer( $hex ), ENT_QUOTES, 'UTF-8' ) . '"', $tag );
			}
			return $tag;
		},
		$html
	);
}

$fout   = 0;
$totaal = 0;
function klopt( $uitkomst, $verwacht, $wat ) {
	$GLOBALS['totaal']++;
	if ( $uitkomst !== $verwacht ) {
		$GLOBALS['fout']++;
		printf( "FOUT  %s\n      gaf      %s\n      verwacht %s\n", $wat, var_export( $uitkomst, true ), var_export( $verwacht, true ) );
	}
}

function pagina( $romp, $kop = '' ) {
	return '<!DOCTYPE html><html><head><title>T</title>' . $kop . '</head><body class="x">' . $romp . '</body></html>';
}

/** Het stuk tussen <body> en het script. */
function romp( $html ) {
	if ( ! preg_match( '~<body class="x">(.*?)(<script id="mm-toolkit-email".*?</script>)?</body>~s', $html, $m ) ) {
		return null;
	}
	return $m[1];
}

$adres = 'marianne@artsenkompas.nl';

// Moeten afgeschermd worden, en na het terugzetten weer gelijk zijn aan het
// origineel. Fragmenten zoals ze op artsenkompas.nl staan.
$afschermen = array(
	'icon-box' => '<div class="elementor-icon-box-wrapper"><div class="elementor-icon-box-icon"> <a href="mailto:marianne@artsenkompas.nl" class="elementor-icon" tabindex="-1" aria-label="marianne@artsenkompas.nl"> <svg aria-hidden="true" class="e-font-icon-svg e-far-envelope" viewBox="0 0 512 512"><path d="M464 64H48z"></path></svg>				</a></div><div class="elementor-icon-box-content"><p class="elementor-icon-box-title"> <a href="mailto:marianne@artsenkompas.nl" > marianne@artsenkompas.nl						</a></p></div></div>',
	'knop'     => '<a class="elementor-button elementor-button-link elementor-size-sm" href="mailto:marianne@artsenkompas.nl"> <span class="elementor-button-content-wrapper"> <span class="elementor-button-text">Mail mij</span> </span> </a>',
	'tekst'    => '<p>Stuur mij een mailtje via <a href="mailto:marianne@artsenkompas.nl">marianne@artsenkompas.nl</a>, en ik zet je op de lijst.</p>',
	'punt'     => '<p>of me mailen op <a href="mailto:marianne@artsenkompas.nl">marianne@artsenkompas.nl</a>.</p>',
	'plat'     => '<p>Mail naar marianne@artsenkompas.nl.</p>',
	'onderwerp' => '<a href="mailto:marianne@artsenkompas.nl?subject=Vraag%20over%20de%20masterclass&amp;body=Hoi">Mail</a>',
	'title'    => '<a href="/contact/" title="Mail marianne@artsenkompas.nl">Contact</a>',
	'alt'      => '<img src="a.png" alt="Adres: marianne@artsenkompas.nl">',
	'quotes'   => "<a href='mailto:marianne@artsenkompas.nl' class=x>Mail</a>",
	'twee'     => '<p>info@a.nl en <b>ik@b.co.uk</b></p>',
	'hoofdletters' => '<A HREF="MAILTO:Info@Example.COM">Info@Example.COM</A>',
);

foreach ( $afschermen as $naam => $fragment ) {
	$uit  = mm_email_afschermen_html( pagina( $fragment ) );
	$deel = romp( $uit );
	klopt( false !== strpos( $uit, '<script id="mm-toolkit-email"' ), true, "$naam: script staat erin" );
	klopt( (bool) preg_match( '~[A-Za-z0-9]@[A-Za-z0-9]~', (string) $deel ), false, "$naam: geen leesbaar adres meer" );
	// Enkele quotes worden dubbele; voor de browser is dat hetzelfde.
	klopt( terugzetten( (string) $deel ), str_replace( "'mailto:marianne@artsenkompas.nl'", '"mailto:marianne@artsenkompas.nl"', $fragment ), "$naam: terugzetten geeft het origineel" );
}

// Hoe het eruitziet zonder JavaScript.
$uit = romp( mm_email_afschermen_html( pagina( $afschermen['tekst'] ) ) );
klopt( (bool) preg_match( '~href="mailto:marianne\[at\]artsenkompas\.nl" data-mm-e="href:[0-9a-f]+">~', $uit ), true, 'zonder JS: href leesbaar' );
klopt( (bool) preg_match( '~<span data-mm-t="[0-9a-f]+">marianne \[at\] artsenkompas\.nl</span>~', $uit ), true, 'zonder JS: tekst leesbaar' );
$uit = romp( mm_email_afschermen_html( pagina( $afschermen['icon-box'] ) ) );
klopt( false !== strpos( $uit, 'aria-label="marianne [at] artsenkompas.nl"' ), true, 'zonder JS: aria-label leesbaar' );
klopt( substr_count( $uit, 'data-mm-e="href:' ), 2, 'icon-box: beide links' );
klopt( (bool) preg_match( '~data-mm-e="href:[0-9a-f]+,aria-label:[0-9a-f]+"~', $uit ), true, 'icon-box: href en aria-label in één attribuut' );
$uit = romp( mm_email_afschermen_html( pagina( $afschermen['plat'] ) ) );
klopt( false !== strpos( $uit, '<a ' ), false, 'plat adres wordt geen link' );
klopt( false !== strpos( $uit, '</span>.</p>' ), true, 'punt achter het adres blijft erbuiten' );

// Met entiteiten, zoals antispambot() het schrijft.
$uit = romp( mm_email_afschermen_html( pagina( '<p>&#109;a&#114;ianne&#64;artsenkompas&#46;nl</p>' ) ) );
klopt( (bool) preg_match( '~^<p><span data-mm-t="([0-9a-f]+)">marianne \[at\] artsenkompas\.nl</span></p>$~', $uit, $m ), true, 'entiteiten: afgeschermd' );
klopt( isset( $m[1] ) ? decodeer( $m[1] ) : null, $adres, 'entiteiten: juiste adres' );
$uit = romp( mm_email_afschermen_html( pagina( '<a href="&#109;&#97;&#105;&#108;&#116;&#111;&#58;marianne&#64;artsenkompas.nl">x</a>' ) ) );
klopt( (bool) preg_match( '~data-mm-e="href:([0-9a-f]+)"~', $uit, $m ), true, 'entiteiten in href: afgeschermd' );
klopt( isset( $m[1] ) ? decodeer( $m[1] ) : null, 'mailto:' . $adres, 'entiteiten in href: juiste waarde' );

// Een aria-label met een é: UTF-8 blijft heel.
$uit = romp( mm_email_afschermen_html( pagina( '<a href="#" aria-label="Mail André: andre@x.nl">x</a>' ) ) );
preg_match( '~aria-label:([0-9a-f]+)~', $uit, $m );
klopt( isset( $m[1] ) ? decodeer( $m[1] ) : null, 'Mail André: andre@x.nl', 'UTF-8 in aria-label' );

// Moeten ongemoeid blijven.
$ongemoeid = array(
	'json-ld'    => '<script type="application/ld+json">{"@type":"FAQPage","text":"mail marianne@artsenkompas.nl"}</script>',
	'script'     => '<script>var a = "<a href=\'mailto:x@y.nl\'>x@y.nl</a>"; if (a<b) {}</script>',
	'style'      => '<style>@media (max-width:1px){a[href^="mailto:x@y.nl"]{color:red}}</style>',
	'textarea'   => '<textarea name="b">x@y.nl</textarea>',
	'formulier'  => '<form class="elementor-form"><input type="email" name="e" value="x@y.nl" placeholder="naam@voorbeeld.nl"><label>x@y.nl</label></form>',
	'input'      => '<input type="email" value="x@y.nl" placeholder="naam@voorbeeld.nl">',
	'commentaar' => '<!-- contact: x@y.nl -->',
	'noscript'   => '<noscript><a href="mailto:x@y.nl">x@y.nl</a></noscript>',
	'data'       => '<div data-settings="{&quot;email&quot;:&quot;x@y.nl&quot;}"></div>',
	'gewone url' => '<a href="https://wa.me/?text=x@y.nl">x</a>',
	'srcset'     => '<img src="logo.png" srcset="logo@2x.png 2x" alt="logo">',
	'bestand'    => '<p>Gebruik logo@2x.png of icon@3x.webp</p>',
	'handle'     => '<p>Volg @artsenkompas en @mariannebrouwers</p>',
	'geen adres' => '<p>Prijs @ 10 euro, a@b</p>',
	'svg'        => '<svg><text>x@y.nl</text></svg>',
	'losse <'    => '<p>a < b en c@d</p>',
);
foreach ( $ongemoeid as $naam => $fragment ) {
	$in = pagina( $fragment );
	klopt( mm_email_afschermen_html( $in ), $in, "$naam: ongemoeid" );
}

// De kop blijft ongemoeid, ook met een adres erin.
$kop = '<meta name="description" content="Mail x@y.nl"><script type="application/ld+json" class="rank-math-schema">{"email":"marianne@artsenkompas.nl"}</script>';
$uit = mm_email_afschermen_html( pagina( '<p>x@y.nl</p>', $kop ) );
klopt( false !== strpos( $uit, '<head><title>T</title>' . $kop . '</head>' ), true, 'kop met JSON-LD ongemoeid' );
klopt( substr_count( $uit, 'marianne@artsenkompas.nl' ), 1, 'Organization-schema houdt het adres' );

// JSON-LD in de body en een adres ervoor en erna.
$fragment = '<p>a@b.nl</p><script type="application/ld+json">{"text":"a@b.nl"}</script><p>c@d.nl</p>';
$uit      = romp( mm_email_afschermen_html( pagina( $fragment ) ) );
klopt( substr_count( $uit, '<span data-mm-t=' ), 2, 'tekst rond JSON-LD afgeschermd' );
klopt( false !== strpos( $uit, '<script type="application/ld+json">{"text":"a@b.nl"}</script>' ), true, 'JSON-LD in de body ongemoeid' );

// Zonder <body>, zonder adres, of al afgeschermd: niets veranderen.
klopt( mm_email_afschermen_html( '{"email":"x@y.nl"}' ), '{"email":"x@y.nl"}', 'JSON zonder body' );
klopt( mm_email_afschermen_html( '<?xml version="1.0"?><urlset><url>x@y.nl</url></urlset>' ), '<?xml version="1.0"?><urlset><url>x@y.nl</url></urlset>', 'XML zonder body' );
$in = pagina( '<p>Geen adres</p>' );
klopt( mm_email_afschermen_html( $in ), $in, 'geen adres: geen script' );
$een = mm_email_afschermen_html( pagina( $afschermen['icon-box'] ) );
klopt( substr_count( mm_email_afschermen_html( $een ), 'id="mm-toolkit-email"' ), 1, 'twee keer afschermen geeft één script' );
klopt( romp( mm_email_afschermen_html( $een ) ), romp( $een ), 'twee keer afschermen verandert niets meer' );
klopt( mm_email_afschermen_html( null ), null, 'null ongemoeid' );

// Zelfde invoer, zelfde uitvoer (voor de cache).
klopt( mm_email_afschermen_html( pagina( $afschermen['tekst'] ) ), mm_email_afschermen_html( pagina( $afschermen['tekst'] ) ), 'zelfde uitvoer bij elk verzoek' );

// Script voor </body>.
$uit = mm_email_afschermen_html( pagina( '<p>x@y.nl</p>' ) );
klopt( (bool) preg_match( '~</script></body></html>$~', $uit ), true, 'script vlak voor </body>' );
klopt( (bool) preg_match( '~<script id="mm-toolkit-email" data-no-defer="1" data-no-optimize="1"~', $uit ), true, 'script buiten LiteSpeed-optimalisatie' );

// Buffer: een tussentijdse flush wordt opgespaard.
$deel1 = substr( pagina( $afschermen['tekst'] ), 0, 70 );
$deel2 = substr( pagina( $afschermen['tekst'] ), 70 );
klopt( mm_email_afschermen_buffer( $deel1, PHP_OUTPUT_HANDLER_START | PHP_OUTPUT_HANDLER_FLUSH ), '', 'flush houdt de uitvoer vast' );
klopt( mm_email_afschermen_buffer( $deel2, PHP_OUTPUT_HANDLER_FINAL ), mm_email_afschermen_html( pagina( $afschermen['tekst'] ) ), 'einde geeft alles afgeschermd' );
mm_email_afschermen_buffer( 'weg', PHP_OUTPUT_HANDLER_FLUSH );
mm_email_afschermen_buffer( '', PHP_OUTPUT_HANDLER_CLEAN );
klopt( mm_email_afschermen_buffer( 'x', PHP_OUTPUT_HANDLER_FINAL ), 'x', 'clean gooit het opgespaarde weg' );

// Hooks.
klopt( isset( $GLOBALS['hooks']['template_redirect'] ) && in_array( 'mm_email_afschermen_start', $GLOBALS['hooks']['template_redirect'], true ), true, 'buffer start op template_redirect' );
klopt( isset( $GLOBALS['hooks']['init'] ) && in_array( 'mm_email_afschermen_shortcode', $GLOBALS['hooks']['init'], true ), true, 'shortcode op init' );

printf( "%d van %d gevallen goed\n", $totaal - $fout, $totaal );
exit( $fout ? 1 : 0 );
