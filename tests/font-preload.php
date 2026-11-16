<?php
/**
 * Mediamora Toolkit, testgevallen voor Lettertype voorladen.
 *
 * Draait los van WordPress, alleen vanaf de opdrachtregel:
 *   php tests/font-preload.php
 * Geeft exitcode 1 als er een geval misgaat.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

define( 'ABSPATH', __DIR__ . '/' );

// Het modulebestand roept bij het laden alleen add_action aan.
function add_action() {}
function trailingslashit( $tekst ) {
	return rtrim( $tekst, '/\\' ) . '/';
}

require dirname( __DIR__ ) . '/modules/font-preload.php';

$fouten = 0;
$aantal = 0;

function controleer( $naam, $kreeg, $verwacht ) {
	global $fouten, $aantal;
	$aantal++;
	if ( $kreeg !== $verwacht ) {
		$fouten++;
		echo "FOUT: $naam\n  verwacht: " . var_export( $verwacht, true ) . "\n  kreeg:    " . var_export( $kreeg, true ) . "\n";
	}
}

$latin  = 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+2000-206F, U+20AC, U+2122, U+FEFF, U+FFFD';
$ext    = 'U+0100-02BA, U+02BD-02C5, U+1E00-1E9F, U+2020, U+20A0-20AB';
$basis  = 'https://voorbeeld.nl/wp-content/uploads/elementor/google-fonts/fonts/';
$blok   = function ( $stijl, $gewicht, $bestand, $bereik ) use ( $basis ) {
	return "/* x */\n@font-face {\n  font-family: 'Montserrat';\n  font-style: $stijl;\n  font-weight: $gewicht;\n  font-display: swap;\n  src: url($basis$bestand) format('woff2');\n  unicode-range: $bereik;\n}\n";
};

// Zoals Elementor het neerzet: per gewicht eerst alle cursieve subsets, dan
// de rechte. Variabel lettertype, dus per stijl één latin-bestand.
$css = '';
foreach ( array( 'italic', 'normal' ) as $stijl ) {
	foreach ( array( 100, 300, 400, 700, 900 ) as $gewicht ) {
		$css .= $blok( $stijl, $gewicht, 'montserrat-ext-' . $stijl . '.woff2', $ext );
		$css .= $blok( $stijl, $gewicht, 'montserrat-' . ( 'italic' === $stijl ? '76393105' : '49e242af' ) . '.woff2', $latin );
	}
}

controleer( 'recht 300 pakt het rechte latin-bestand, niet het cursieve', mm_font_preload_zoek_url( $css, 300, 'normal' ), $basis . 'montserrat-49e242af.woff2' );
controleer( 'cursief 400', mm_font_preload_zoek_url( $css, 400, 'italic' ), $basis . 'montserrat-76393105.woff2' );
controleer( 'gewicht dat niet bestaat', mm_font_preload_zoek_url( $css, 500, 'normal' ), '' );
controleer( 'lege css', mm_font_preload_zoek_url( '', 400, 'normal' ), '' );

// Statisch lettertype: per gewicht een eigen bestand.
$statisch = $blok( 'normal', 400, 'kleeone-400.woff2', $latin ) . $blok( 'normal', 600, 'kleeone-600.woff2', $latin ) . $blok( 'normal', 600, 'kleeone-jp.woff2', 'U+25ee8, U+25f23' );
controleer( 'statisch 600', mm_font_preload_zoek_url( $statisch, 600, 'normal' ), $basis . 'kleeone-600.woff2' );
controleer( 'alleen japanse subset telt niet', mm_font_preload_zoek_url( $blok( 'normal', 400, 'jp.woff2', 'U+25ee8' ), 400, 'normal' ), '' );

// Bereik bij een variabel lettertype, url tussen aanhalingstekens, geen font-style.
$bereik = "@font-face{font-family:'X';font-weight:100 900;src:url('{$basis}x-var.woff2') format('woff2');unicode-range:$latin}";
controleer( 'gewichtsbereik 100 900', mm_font_preload_zoek_url( $bereik, 300, 'normal' ), $basis . 'x-var.woff2' );
controleer( 'buiten het bereik', mm_font_preload_zoek_url( str_replace( '100 900', '400 700', $bereik ), 300, 'normal' ), '' );

// Latin-ext begint niet met U+0000-00FF en telt dus niet, ook niet als
// U+0000-00FF later in de lijst zou staan.
controleer( 'latin-ext', mm_font_preload_zoek_url( $blok( 'normal', 400, 'ext.woff2', $ext . ', U+0000-00FF' ), 400, 'normal' ), '' );

// Geen woff2 in src.
controleer( 'alleen woff', mm_font_preload_zoek_url( str_replace( '.woff2', '.woff', $blok( 'normal', 400, 'a.woff2', $latin ) ), 400, 'normal' ), '' );

// Gewicht uit de kit.
controleer( 'gewicht leeg', mm_font_preload_gewicht( '' ), 400 );
controleer( 'gewicht normal', mm_font_preload_gewicht( 'normal' ), 400 );
controleer( 'gewicht bold', mm_font_preload_gewicht( 'bold' ), 700 );
controleer( 'gewicht 300', mm_font_preload_gewicht( '300' ), 300 );
controleer( 'gewicht onzin', mm_font_preload_gewicht( '3x' ), 400 );

// Typografie uit de kit.
$kit = array(
	'system_typography' => array(
		array( '_id' => 'primary', 'typography_font_family' => 'Klee One', 'typography_font_weight' => '600' ),
		array( '_id' => 'secondary', 'typography_font_family' => 'Montserrat', 'typography_font_weight' => '400' ),
		array( '_id' => 'text', 'typography_font_family' => 'Montserrat', 'typography_font_weight' => '300', 'typography_font_style' => 'normal' ),
		array( '_id' => 'accent', 'typography_font_family' => '' ),
	),
);
controleer( 'kit text', mm_font_preload_kit_typografie( $kit, 'text' ), array( 'familie' => 'Montserrat', 'gewicht' => 300, 'stijl' => 'normal' ) );
controleer( 'kit primary', mm_font_preload_kit_typografie( $kit, 'primary' ), array( 'familie' => 'Klee One', 'gewicht' => 600, 'stijl' => 'normal' ) );
controleer( 'kit zonder lettertype', mm_font_preload_kit_typografie( $kit, 'accent' ), null );
controleer( 'kit zonder dit id', mm_font_preload_kit_typografie( $kit, 'bestaat-niet' ), null );
controleer( 'kit leeg', mm_font_preload_kit_typografie( array(), 'text' ), null );
$cursief = array( 'system_typography' => array( array( '_id' => 'text', 'typography_font_family' => 'Lora', 'typography_font_style' => 'italic' ) ) );
controleer( 'kit cursief', mm_font_preload_kit_typografie( $cursief, 'text' ), array( 'familie' => 'Lora', 'gewicht' => 400, 'stijl' => 'italic' ) );

// Pad van het css-bestand.
$uploads = array( 'baseurl' => 'https://voorbeeld.nl/wp-content/uploads', 'basedir' => '/srv/www/wp-content/uploads' );
controleer( 'css-pad montserrat', mm_font_preload_css_pad( 'Montserrat', $uploads ), '/srv/www/wp-content/uploads/elementor/google-fonts/css/montserrat.css' );
controleer( 'css-pad Klee One', mm_font_preload_css_pad( 'Klee One', $uploads ), '/srv/www/wp-content/uploads/elementor/google-fonts/css/kleeone.css' );
controleer( 'css-pad met rare tekens', mm_font_preload_css_pad( '../etc', $uploads ), '' );

// Pad van het woff2-bestand.
controleer( 'pad https', mm_font_preload_pad( $basis . 'montserrat-49e242af.woff2', $uploads ), '/srv/www/wp-content/uploads/elementor/google-fonts/fonts/montserrat-49e242af.woff2' );
controleer( 'pad http in de css', mm_font_preload_pad( str_replace( 'https:', 'http:', $basis ) . 'a.woff2', $uploads ), '/srv/www/wp-content/uploads/elementor/google-fonts/fonts/a.woff2' );
controleer( 'pad met query', mm_font_preload_pad( $basis . 'a.woff2?ver=1', $uploads ), '/srv/www/wp-content/uploads/elementor/google-fonts/fonts/a.woff2' );
controleer( 'pad ander domein', mm_font_preload_pad( 'https://fonts.gstatic.com/s/a.woff2', $uploads ), '' );
controleer( 'pad buiten de map', mm_font_preload_pad( $basis . '../css/a.woff2', $uploads ), '' );
controleer( 'pad submap', mm_font_preload_pad( $basis . 'sub/a.woff2', $uploads ), '' );

echo $fouten ? "$fouten van $aantal gevallen fout\n" : "Alle $aantal gevallen goed\n";
exit( $fouten ? 1 : 0 );
