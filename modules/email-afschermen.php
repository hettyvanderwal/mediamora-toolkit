<?php
/**
 * Mediamora Toolkit, module: E-mailadressen afschermen
 * Vervangt Email Address Obfuscator van ASE en losse code in functions.php.
 * Wordt alleen geladen als de module aanstaat.
 *
 * Werkt op de uiteindelijke HTML van de voorkant, via een output buffer vanaf
 * template_redirect. Zo valt alles eronder wat Elementor rendert, ook de
 * header en footer van de Theme Builder, widgets, the_content en shortcodes,
 * zonder per widget een filter. De buffer zit binnen die van LiteSpeed Cache,
 * dus LiteSpeed optimaliseert en cachet de HTML die al is afgeschermd.
 *
 * Aanpak, per plek:
 * - mailto-link: href wordt mailto:info[at]domein.nl, en het echte adres staat
 *   versleuteld in data-mm-e. Een klein script zet de echte href terug.
 * - aria-label, title en alt met een adres erin: zelfde aanpak, met
 *   "info [at] domein.nl" in de plaats.
 * - zichtbare tekst: het adres wordt <span data-mm-t="...">info [at]
 *   domein.nl</span>. Het script vervangt de span door het echte adres als
 *   gewone tekst, dus het ziet er normaal uit en kopiëren geeft het juiste
 *   adres. Een los adres wordt niet klikbaar gemaakt.
 * Zonder JavaScript blijft "info [at] domein.nl" staan: leesbaar, en een link
 * opent het mailprogramma met dat adres, dat de bezoeker zelf kan verbeteren.
 *
 * Versleuteling: per waarde een sleutelbyte, daarna elke byte van de UTF-8-
 * tekst XOR die sleutel, als hex. Geen beveiliging, wel genoeg om bots die
 * naar een @ of mailto: zoeken niets te laten vinden. De sleutel volgt uit de
 * waarde zelf, zodat dezelfde pagina steeds dezelfde HTML geeft.
 *
 * Wordt niet aangeraakt: alles vóór <body> (meta-tags, JSON-LD van Rank Math),
 * de inhoud van script, style, textarea, noscript, template, form, select,
 * svg, iframe, object en math (dus ook JSON-LD in de body, zoals het
 * FAQ-schema van de accordeon), HTML-commentaar, andere attributen dan href,
 * aria-label, title en alt (dus geen value, placeholder of data-settings), en
 * hrefs die niet met mailto: beginnen. De buffer start niet in het beheer, de
 * Elementor-editor en het voorbeeld, de customizer, REST, AJAX, cron, feeds,
 * XML-RPC en WP-CLI; wp-login.php komt niet bij template_redirect. Uitgaande
 * mail gaat niet door de buffer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Elementen waarvan de inhoud ongemoeid blijft. */
const MM_EMAIL_OVERSLAAN = array( 'script', 'style', 'textarea', 'noscript', 'template', 'form', 'select', 'svg', 'iframe', 'object', 'math' );

/** Attributen die worden afgeschermd. href alleen bij mailto:. */
const MM_EMAIL_ATTRIBUTEN = array( 'href', 'aria-label', 'title', 'alt' );

/** Een e-mailadres in gewone tekst (zonder entiteiten). */
const MM_EMAIL_PATROON = '(?<![A-Za-z0-9._%+\-])([A-Za-z0-9._%+\-]+)@([A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?)*\.([A-Za-z]{2,24}))(?![A-Za-z0-9\-])';

/**
 * Bestandsextensies die op een topleveldomein lijken, zoals logo@2x.png.
 */
const MM_EMAIL_GEEN_TLD = array( 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg', 'css', 'js' );

/**
 * Hoort de buffer bij dit verzoek? Alleen de gewone voorkant.
 */
function mm_email_afschermen_nodig() {

	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return false;
	}

	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return false;
	}

	if ( is_feed() || is_robots() || is_trackback() || is_preview() || is_customize_preview() ) {
		return false;
	}

	if ( isset( $_GET['elementor-preview'] ) ) {
		return false;
	}

	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) ) {
		$elementor = \Elementor\Plugin::$instance;
		if ( isset( $elementor->preview ) && method_exists( $elementor->preview, 'is_preview_mode' ) && $elementor->preview->is_preview_mode() ) {
			return false;
		}
		if ( isset( $elementor->editor ) && method_exists( $elementor->editor, 'is_edit_mode' ) && $elementor->editor->is_edit_mode() ) {
			return false;
		}
	}

	/**
	 * Per verzoek uit te zetten, bijvoorbeeld voor één pagina.
	 *
	 * @param bool $aan
	 */
	return (bool) apply_filters( 'mm_email_afschermen', true );
}

add_action( 'template_redirect', 'mm_email_afschermen_start', 1 );

function mm_email_afschermen_start() {
	if ( mm_email_afschermen_nodig() ) {
		ob_start( 'mm_email_afschermen_buffer' );
	}
}

/**
 * Callback van de buffer. Een tussentijdse flush wordt opgespaard tot het
 * einde, want een stuk HTML kan midden in een tag ophouden.
 *
 * @param string $html
 * @param int    $fase
 * @return string
 */
function mm_email_afschermen_buffer( $html, $fase ) {
	static $verzameld = '';

	if ( $fase & PHP_OUTPUT_HANDLER_CLEAN ) {
		$verzameld = '';
		return '';
	}

	$verzameld .= $html;
	if ( ! ( $fase & PHP_OUTPUT_HANDLER_FINAL ) ) {
		return '';
	}

	$alles     = $verzameld;
	$verzameld = '';

	// Geen HTML, zoals een sitemap of een JSON-antwoord van een plugin.
	if ( function_exists( 'headers_list' ) ) {
		foreach ( headers_list() as $header ) {
			if ( 0 === stripos( $header, 'content-type:' ) && false === stripos( $header, 'text/html' ) ) {
				return $alles;
			}
		}
	}

	try {
		return mm_email_afschermen_html( $alles );
	} catch ( Throwable $e ) {
		return $alles;
	}
}

/**
 * Schermt de adressen in een hele HTML-pagina af en zet het script voor
 * </body>. Zonder <body>, of als er niets te doen is, komt de HTML ongemoeid
 * terug.
 *
 * @param string $html
 * @return string
 */
function mm_email_afschermen_html( $html ) {

	if ( ! is_string( $html ) || ( false === strpos( $html, '@' ) && false === strpos( $html, '&#' ) && false === stripos( $html, '&commat;' ) ) ) {
		return $html;
	}

	if ( ! preg_match( '~<body[\s>]~i', $html, $m, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}

	$start = $m[0][1];
	$romp  = substr( $html, $start );
	$nieuw = mm_email_afschermen_romp( $romp );

	if ( $nieuw === $romp ) {
		return $html;
	}

	$script = mm_email_afschermen_script();
	$eind   = strripos( $nieuw, '</body' );
	$nieuw  = false === $eind ? $nieuw . $script : substr( $nieuw, 0, $eind ) . $script . substr( $nieuw, $eind );

	return substr( $html, 0, $start ) . $nieuw;
}

/**
 * Loopt de HTML vanaf <body> door: tekst, tags, commentaar en de elementen
 * die worden overgeslagen. Zonder grote regex over de hele pagina, zodat een
 * lang script geen backtracklimiet raakt.
 *
 * @param string $html
 * @return string
 */
function mm_email_afschermen_romp( $html ) {

	$uit    = '';
	$pos    = 0;
	$lengte = strlen( $html );

	while ( $pos < $lengte ) {

		$lt = strpos( $html, '<', $pos );
		if ( false === $lt ) {
			$uit .= mm_email_afschermen_tekst( substr( $html, $pos ) );
			break;
		}
		if ( $lt > $pos ) {
			$uit .= mm_email_afschermen_tekst( substr( $html, $pos, $lt - $pos ) );
		}

		if ( '<!--' === substr( $html, $lt, 4 ) ) {
			$eind = strpos( $html, '-->', $lt + 4 );
			$eind = false === $eind ? $lengte : $eind + 3;
			$uit .= substr( $html, $lt, $eind - $lt );
			$pos  = $eind;
			continue;
		}

		if ( ! preg_match( '~\G<(/?)([a-zA-Z][^\s/>]*)(?:[^>"\']|"[^"]*"|\'[^\']*\')*>~', $html, $m, 0, $lt ) ) {
			// Een losse < in de tekst, of een doctype of iets anders vreemds.
			$uit .= '<';
			$pos  = $lt + 1;
			continue;
		}

		$tag  = $m[0];
		$naam = strtolower( $m[2] );
		$pos  = $lt + strlen( $tag );

		if ( '' !== $m[1] ) {
			$uit .= $tag;
			continue;
		}

		if ( in_array( $naam, MM_EMAIL_OVERSLAAN, true ) ) {
			$uit .= $tag;
			if ( '/>' === substr( $tag, -2 ) ) {
				continue;
			}
			$sluit = mm_email_afschermen_sluittag( $html, $naam, $pos );
			$eind  = false === $sluit ? $lengte : $sluit;
			$uit  .= substr( $html, $pos, $eind - $pos );
			$pos   = $eind;
			continue;
		}

		$uit .= mm_email_afschermen_tag( $tag );
	}

	return $uit;
}

/**
 * Positie van het begin van de sluittag </naam>, of false.
 */
function mm_email_afschermen_sluittag( $html, $naam, $vanaf ) {
	$lengte = strlen( $naam ) + 2;
	while ( false !== ( $sluit = stripos( $html, '</' . $naam, $vanaf ) ) ) {
		$volgend = substr( $html, $sluit + $lengte, 1 );
		if ( '' === $volgend || '>' === $volgend || ctype_space( $volgend ) ) {
			return $sluit;
		}
		$vanaf = $sluit + $lengte;
	}
	return false;
}

/**
 * Adressen in een stuk tekst tussen tags. Kent ook adressen die met
 * entiteiten zijn geschreven, zoals antispambot() ze maakt.
 *
 * @param string $tekst
 * @return string
 */
function mm_email_afschermen_tekst( $tekst ) {

	if ( false === strpos( $tekst, '@' ) && false === strpos( $tekst, '&' ) ) {
		return $tekst;
	}

	$ent    = '&#(?:[0-9]{2,3}|[xX][0-9a-fA-F]{2});';
	$lokaal = '(?:[A-Za-z0-9._%+\-]|' . $ent . ')';
	$apen   = '(?:@|&#0*64;|&#[xX]0*40;|&commat;)';
	$domein = '(?:[A-Za-z0-9.\-]|' . $ent . '|&period;)';

	$uit = preg_replace_callback(
		'~(?<![A-Za-z0-9._%+\-])' . $lokaal . '+' . $apen . $domein . '+~',
		function ( $m ) {
			$leesbaar = html_entity_decode( $m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( ! preg_match( '~^' . MM_EMAIL_PATROON . '~', $leesbaar, $adres ) || in_array( strtolower( $adres[3] ), MM_EMAIL_GEEN_TLD, true ) ) {
				return $m[0];
			}
			$rest = (string) substr( $leesbaar, strlen( $adres[0] ) );
			return '<span data-mm-t="' . mm_email_afschermen_codeer( $adres[0] ) . '">' . mm_email_afschermen_esc( $adres[1] . ' [at] ' . $adres[2] ) . '</span>' . mm_email_afschermen_esc( $rest );
		},
		$tekst
	);

	return null === $uit ? $tekst : $uit;
}

/**
 * Adressen in de attributen van één openingstag.
 *
 * @param string $tag
 * @return string
 */
function mm_email_afschermen_tag( $tag ) {

	if ( false === strpos( $tag, '@' ) && false === strpos( $tag, '&' ) && false === strpos( $tag, '%' ) ) {
		return $tag;
	}

	// Al gedaan, bijvoorbeeld door een tweede buffer.
	if ( false !== stripos( $tag, 'data-mm-e=' ) ) {
		return $tag;
	}

	$gecodeerd = array();

	$nieuw = preg_replace_callback(
		'~(\s)([^\s"\'>/=]+)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?~',
		function ( $m ) use ( &$gecodeerd ) {
			$naam = strtolower( $m[2] );
			if ( ! isset( $m[3] ) || ! in_array( $naam, MM_EMAIL_ATTRIBUTEN, true ) ) {
				return $m[0];
			}

			$ruw = $m[3];
			if ( '"' === $ruw[0] || "'" === $ruw[0] ) {
				$ruw = substr( $ruw, 1, -1 );
			}
			$waarde = html_entity_decode( $ruw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

			if ( 'href' === $naam ) {
				if ( ! preg_match( '~^\s*mailto:~i', $waarde ) ) {
					return $m[0];
				}
				$vervangen = mm_email_afschermen_vervang( str_ireplace( '%40', '@', $waarde ), '[at]' );
			} else {
				$vervangen = mm_email_afschermen_vervang( $waarde, ' [at] ' );
			}

			if ( null === $vervangen ) {
				return $m[0];
			}

			$gecodeerd[] = $naam . ':' . mm_email_afschermen_codeer( $waarde );
			return $m[1] . $m[2] . '="' . mm_email_afschermen_esc( $vervangen ) . '"';
		},
		$tag
	);

	if ( null === $nieuw || ! $gecodeerd ) {
		return $tag;
	}

	return preg_replace( '~\s*/?>$~', ' data-mm-e="' . implode( ',', $gecodeerd ) . '"$0', $nieuw, 1 );
}

/**
 * Vervangt elk adres in een gewone tekst door de leesbare vorm.
 *
 * @param string $waarde
 * @param string $apen   Wat er in de plaats van de @ komt.
 * @return string|null Null als er geen adres in stond.
 */
function mm_email_afschermen_vervang( $waarde, $apen ) {
	$aantal = 0;
	$uit    = preg_replace_callback(
		'~' . MM_EMAIL_PATROON . '~',
		function ( $m ) use ( $apen, &$aantal ) {
			if ( in_array( strtolower( $m[3] ), MM_EMAIL_GEEN_TLD, true ) ) {
				return $m[0];
			}
			$aantal++;
			return $m[1] . $apen . $m[2];
		},
		$waarde
	);
	return ( null === $uit || ! $aantal ) ? null : $uit;
}

/**
 * Sleutelbyte en daarna elke byte XOR die sleutel, als hex. De sleutel is
 * oneven, dus nooit 0.
 *
 * @param string $tekst
 * @return string
 */
function mm_email_afschermen_codeer( $tekst ) {
	$sleutel = ( crc32( $tekst ) & 0xfe ) | 1;
	$uit     = sprintf( '%02x', $sleutel );
	$lengte  = strlen( $tekst );
	for ( $i = 0; $i < $lengte; $i++ ) {
		$uit .= sprintf( '%02x', ord( $tekst[ $i ] ) ^ $sleutel );
	}
	return $uit;
}

/**
 * Escapen voor tekst en attributen. Geen esc_attr(): die laat bestaande
 * entiteiten staan, en hier komt altijd gedecodeerde tekst binnen.
 */
function mm_email_afschermen_esc( $tekst ) {
	return htmlspecialchars( $tekst, ENT_QUOTES, 'UTF-8' );
}

/**
 * Het script dat de echte adressen terugzet. Draait meteen, aan het eind van
 * de body, dus vóór DOMContentLoaded en vóór Elementor zijn links uitleest.
 * data-no-defer en data-no-optimize houden LiteSpeed eraf: uitgesteld zou
 * de leesbare vorm blijven staan tot de bezoeker iets doet. data-cfasync
 * doet hetzelfde voor Rocket Loader van Cloudflare.
 */
function mm_email_afschermen_script() {
	return '<script id="mm-toolkit-email" data-no-defer="1" data-no-optimize="1" data-no-minify="1" data-cfasync="false">'
		. '(function(){'
		. 'function d(h){var k=parseInt(h.substr(0,2),16),s="";for(var i=2;i<h.length;i+=2){s+="%"+("0"+(parseInt(h.substr(i,2),16)^k).toString(16)).slice(-2);}try{return decodeURIComponent(s);}catch(e){return null;}}'
		. 'var n=document.querySelectorAll("[data-mm-t]"),i,j,p,w;'
		. 'for(i=0;i<n.length;i++){w=d(n[i].getAttribute("data-mm-t"));if(w!==null&&n[i].parentNode){n[i].parentNode.replaceChild(document.createTextNode(w),n[i]);}}'
		. 'n=document.querySelectorAll("[data-mm-e]");'
		. 'for(i=0;i<n.length;i++){p=n[i].getAttribute("data-mm-e").split(",");for(j=0;j<p.length;j++){w=p[j].split(":");if(w.length===2&&(w[1]=d(w[1]))!==null){n[i].setAttribute(w[0],w[1]);}}n[i].removeAttribute("data-mm-e");}'
		. '})();'
		. '</script>';
}

/**
 * De shortcode [obfuscate] van ASE. Gaat Email Address Obfuscator in ASE uit,
 * dan stond de shortcode anders als tekst op de pagina. Deze versie zet het
 * adres gewoon neer, als link bij link="yes" of link="mailto", en de buffer
 * schermt het daarna af. Zolang ASE de shortcode zelf nog registreert, blijft
 * die van ASE staan.
 */
add_action( 'init', 'mm_email_afschermen_shortcode', 20 );

function mm_email_afschermen_shortcode() {
	if ( ! shortcode_exists( 'obfuscate' ) ) {
		add_shortcode( 'obfuscate', 'mm_email_afschermen_obfuscate' );
	}
}

function mm_email_afschermen_obfuscate( $atts ) {
	$atts = shortcode_atts(
		array(
			'email'   => '',
			'subject' => '',
			'text'    => '',
			'display' => 'newline',
			'link'    => 'no',
			'class'   => '',
		),
		$atts,
		'obfuscate'
	);

	$adres = sanitize_email( $atts['email'] );
	if ( ! is_email( $adres ) ) {
		return '';
	}

	$tekst = '' !== $atts['text'] ? esc_html( $atts['text'] ) : esc_html( $adres );
	if ( in_array( $atts['link'], array( 'yes', 'mailto' ), true ) ) {
		$onderwerp = '' !== $atts['subject'] ? '?subject=' . rawurlencode( sanitize_text_field( $atts['subject'] ) ) : '';
		$tekst     = '<a href="' . esc_attr( 'mailto:' . $adres . $onderwerp ) . '">' . $tekst . '</a>';
	}

	$stijl = 'inline' === $atts['display'] ? 'display:inline;' : 'display:block;';
	return '<span style="' . $stijl . '" class="' . esc_attr( sanitize_text_field( $atts['class'] ) ) . '">' . $tekst . '</span>';
}

/**
 * Staat Email Address Obfuscator in ASE nog aan? Voor de melding in het
 * instellingenscherm.
 */
function mm_email_afschermen_ase_aan() {
	$ase = get_option( 'admin_site_enhancements', array() );
	return is_array( $ase ) && ! empty( $ase['obfuscate_email_address'] );
}
