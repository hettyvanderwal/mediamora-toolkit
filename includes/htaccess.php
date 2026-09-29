<?php
/**
 * Mediamora Toolkit, de blokken van de toolkit in .htaccess.
 *
 * Twee blokken, allebei alleen op LiteSpeed en allebei boven # BEGIN
 * WordPress:
 * - Preview-link: zie "Preview-link: LiteSpeed-cache overslaan" in
 *   mediamora-toolkit.php.
 * - AI-bots: zie "AI-bots: LiteSpeed-cache overslaan" in mediamora-toolkit.php.
 *
 * Staat in een eigen bestand zodat ook uninstall.php het kan laden. Dat
 * draait zonder de rest van de plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MM_TOOLKIT_PREVIEW_MARKER = 'Mediamora Preview';
const MM_TOOLKIT_AIBOTS_MARKER  = 'Mediamora AI-bots';

/**
 * Draait de site op LiteSpeed? Null als dat niet te zien is, bijvoorbeeld
 * via WP-CLI of cron zonder webserver.
 */
function mm_toolkit_litespeed_server() {
	$software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? (string) $_SERVER['SERVER_SOFTWARE'] : '';
	if ( '' === $software ) {
		return null;
	}
	return false !== stripos( $software, 'litespeed' );
}

function mm_toolkit_preview_htaccess_blok() {
	return '# BEGIN ' . MM_TOOLKIT_PREVIEW_MARKER . "\n"
		. "# Preview-link van de Mediamora Toolkit: LiteSpeed slaat de cache over\n"
		. "# voor bezoekers met het preview-cookie. Wordt door de plugin beheerd.\n"
		. "<IfModule LiteSpeed>\n"
		. "RewriteEngine On\n"
		. "RewriteCond %{HTTP_COOKIE} (^|;\\s*)mm_preview=\n"
		. "RewriteRule .* - [E=Cache-Control:no-cache]\n"
		. "</IfModule>\n"
		. '# END ' . MM_TOOLKIT_PREVIEW_MARKER . "\n";
}

/**
 * Het AI-bots-blok. Alleen namen met letters, cijfers, - en _ komen erin,
 * zodat er niets in de reguliere expressie terechtkomt dat daar iets anders
 * betekent.
 *
 * @param string[] $bots User agents, zoals in mm_aibots_lijst().
 * @return string Leeg als er geen bruikbare naam over is.
 */
function mm_toolkit_aibots_htaccess_blok( $bots ) {
	$bots = array_values( array_filter( (array) $bots, function ( $bot ) {
		return is_string( $bot ) && preg_match( '/^[A-Za-z0-9_-]+$/', $bot );
	} ) );
	if ( ! $bots ) {
		return '';
	}
	return '# BEGIN ' . MM_TOOLKIT_AIBOTS_MARKER . "\n"
		. "# AI-bots van de Mediamora Toolkit: LiteSpeed slaat de cache over voor\n"
		. "# bekende AI-crawlers, zodat elk bezoek geteld wordt. Wordt door de plugin beheerd.\n"
		. "<IfModule LiteSpeed>\n"
		. "RewriteEngine On\n"
		. 'RewriteCond %{HTTP_USER_AGENT} (' . implode( '|', $bots ) . ") [NC]\n"
		. "RewriteRule .* - [E=Cache-Control:no-cache]\n"
		. "</IfModule>\n"
		. '# END ' . MM_TOOLKIT_AIBOTS_MARKER . "\n";
}

/**
 * Hoe vaak staat een markerregel in de tekst? Telt alleen hele regels.
 */
function mm_toolkit_htaccess_tel( $inhoud, $regel ) {
	return (int) preg_match_all( '/^' . preg_quote( $regel, '/' ) . '[ \t]*\r?$/m', $inhoud );
}

/**
 * Preview-blok bijwerken.
 *
 * @param bool $gewenst Moet het blok erin staan?
 * @return bool True als .htaccess daarna in orde is.
 */
function mm_toolkit_preview_cache_bijwerken( $gewenst ) {
	return mm_toolkit_htaccess_blok_bijwerken(
		MM_TOOLKIT_PREVIEW_MARKER,
		$gewenst ? mm_toolkit_preview_htaccess_blok() : '',
		$gewenst,
		'mm_toolkit_preview_htaccess_fout'
	);
}

/**
 * AI-bots-blok bijwerken.
 *
 * De lijst met crawlers staat in de module. Laadt die (nog) niet, dan kan
 * het blok niet gemaakt worden en gebeurt er niets. Dat is het geval direct
 * na het aanzetten van de module; het zelfherstel zet het blok dan bij de
 * volgende beheerpagina. Weghalen kan altijd.
 *
 * @param bool $gewenst Moet het blok erin staan?
 * @return bool True als .htaccess daarna in orde is.
 */
function mm_toolkit_aibots_cache_bijwerken( $gewenst ) {
	$blok = '';
	if ( $gewenst ) {
		if ( ! function_exists( 'mm_aibots_cache_bots' ) ) {
			return false;
		}
		$blok = mm_toolkit_aibots_htaccess_blok( mm_aibots_cache_bots() );
	}
	return mm_toolkit_htaccess_blok_bijwerken(
		MM_TOOLKIT_AIBOTS_MARKER,
		$blok,
		$gewenst,
		'mm_toolkit_aibots_htaccess_fout'
	);
}

/**
 * Zet een blok bovenaan .htaccess of haalt het weg.
 *
 * Toevoegen gebeurt alleen op LiteSpeed. Weghalen mag altijd, maar het
 * bestand wordt alleen aangeraakt als er echt iets verandert. Zonder
 * LiteSpeed en zonder blok gebeurt er dus niets.
 *
 * Het blok moet vóór # BEGIN WordPress staan: de WordPress-regel eindigt op
 * [L], dus alles daarna wordt voor pagina's nooit gelezen. Daarom geen
 * insert_with_markers(), want die zet een nieuw blok onderaan.
 *
 * Na het schrijven wordt het bestand teruggelezen. Staan # BEGIN WordPress
 * en het eigen blok er dan niet elk precies zo vaak in als bedoeld, of staat
 * het blok niet boven WordPress, dan komt de vorige inhoud terug.
 *
 * Elk blok haalt alleen zijn eigen marker weg, dus de blokken van de
 * toolkit kunnen naast elkaar boven WordPress staan.
 *
 * @param string $marker  Naam na # BEGIN en # END.
 * @param string $blok    Het hele blok met markers. Leeg bij weghalen.
 * @param bool   $gewenst Moet het blok erin staan?
 * @param string $fout    Transient die een mislukte poging onthoudt.
 * @return bool True als .htaccess daarna in orde is.
 */
function mm_toolkit_htaccess_blok_bijwerken( $marker, $blok, $gewenst, $fout ) {

	if ( $gewenst && ( '' === $blok || true !== mm_toolkit_litespeed_server() ) ) {
		return false;
	}

	if ( ! function_exists( 'get_home_path' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	$pad = get_home_path() . '.htaccess';

	if ( ! file_exists( $pad ) ) {
		// Geen .htaccess betekent ook geen WordPress-blok. Niet zelf aanmaken.
		return ! $gewenst;
	}

	$oud = file_get_contents( $pad );
	if ( false === $oud ) {
		return mm_toolkit_htaccess_fout( $gewenst, $fout );
	}

	$begin = '# BEGIN ' . $marker;
	$einde = '# END ' . $marker;

	$zonder = preg_replace(
		'/^' . preg_quote( $begin, '/' ) . '[ \t]*\r?$.*?^' . preg_quote( $einde, '/' ) . '[ \t]*\r?$\R*/ms',
		'',
		$oud
	);
	if ( null === $zonder ) {
		return mm_toolkit_htaccess_fout( $gewenst, $fout );
	}
	$nieuw = $gewenst ? $blok . "\n" . $zonder : $zonder;

	if ( $nieuw === $oud ) {
		delete_transient( $fout );
		return true;
	}

	// Zonder precies één WordPress-blok is het bestand niet wat we verwachten.
	// Dan liever niets doen dan gokken waar het blok moet.
	if ( 1 !== mm_toolkit_htaccess_tel( $oud, '# BEGIN WordPress' ) || ! is_writable( $pad ) ) {
		return mm_toolkit_htaccess_fout( $gewenst, $fout );
	}

	if ( false === file_put_contents( $pad, $nieuw, LOCK_EX ) ) {
		return mm_toolkit_htaccess_fout( $gewenst, $fout );
	}

	clearstatcache( true, $pad );
	$terug  = file_get_contents( $pad );
	$aantal = $gewenst ? 1 : 0;
	$goed   = false !== $terug
		&& 1 === mm_toolkit_htaccess_tel( $terug, '# BEGIN WordPress' )
		&& $aantal === mm_toolkit_htaccess_tel( $terug, $begin )
		&& $aantal === mm_toolkit_htaccess_tel( $terug, $einde )
		&& ( ! $gewenst || strpos( $terug, $begin ) < strpos( $terug, '# BEGIN WordPress' ) );

	if ( ! $goed ) {
		file_put_contents( $pad, $oud, LOCK_EX );
		return mm_toolkit_htaccess_fout( $gewenst, $fout );
	}

	delete_transient( $fout );
	return true;
}

/**
 * Onthoudt een mislukte poging, zodat het zelfherstel het niet bij elke
 * beheerpagina opnieuw probeert en het instellingenscherm het kan tonen.
 * Een mislukte opruiming telt niet: een blijvend blok is onschuldig.
 */
function mm_toolkit_htaccess_fout( $gewenst, $fout ) {
	if ( $gewenst ) {
		set_transient( $fout, 1, DAY_IN_SECONDS );
	}
	return false;
}
