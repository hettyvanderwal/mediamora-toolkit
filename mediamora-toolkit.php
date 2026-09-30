<?php
/**
 * Plugin Name:       Mediamora Toolkit
 * Plugin URI:        https://github.com/hettyvanderwal/mediamora-toolkit
 * Description:       De vaste Mediamora-onderdelen in één plugin, per onderdeel aan en uit te zetten onder Instellingen > Mediamora Toolkit.
 * Version:           1.8.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Update URI:        https://github.com/hettyvanderwal/mediamora-toolkit
 * Author:            Mediamora
 * Author URI:        https://mediamora.nl
 * License:           GPL-2.0-or-later
 * Text Domain:       mediamora-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Versienummer. Gelijk houden aan "Version" in de kop hierboven. Voor
 * updates telt alleen de kop, zie mm_toolkit_huidige_versie(); deze
 * constante is er voor de rest, zoals de mailheader van de Formuliermonitor.
 */
const MM_TOOLKIT_VERSIE = '1.8.0';
const MM_TOOLKIT_REPO   = 'hettyvanderwal/mediamora-toolkit';
const MM_TOOLKIT_SLUG   = 'mediamora-toolkit';
const MM_TOOLKIT_OPTIE  = 'mm_toolkit_modules';

define( 'MM_TOOLKIT_BESTAND', __FILE__ );
define( 'MM_TOOLKIT_MAP', __DIR__ );

require_once MM_TOOLKIT_MAP . '/includes/modules.php';
require_once MM_TOOLKIT_MAP . '/includes/htaccess.php';
require_once MM_TOOLKIT_MAP . '/includes/herroeping.php';

/**
 * Staat er nog een losse versie van deze module op de site?
 */
function mm_toolkit_losse_versie( $module ) {

	if ( $module['losse_mu'] && defined( 'WPMU_PLUGIN_DIR' ) && file_exists( WPMU_PLUGIN_DIR . '/' . $module['losse_mu'] ) ) {
		return 'mu-plugin ' . $module['losse_mu'];
	}

	if ( $module['losse_plugin'] && in_array( $module['losse_plugin'], (array) get_option( 'active_plugins', array() ), true ) ) {
		return 'plugin ' . dirname( $module['losse_plugin'] );
	}

	if ( ! empty( $module['merkteken'] ) ) {
		list( $soort, $naam ) = $module['merkteken'];
		if ( ( 'class' === $soort && class_exists( $naam, false ) ) || ( 'function' === $soort && function_exists( $naam ) ) ) {
			return 'een losse versie';
		}
	}

	return '';
}

/**
 * Vastgezet via wp-config? Geeft true, false of null (niet vastgezet).
 *
 * Per site vastzetten kan in wp-config.php, bijvoorbeeld:
 *   define( 'MM_TOOLKIT_MODULE_ALT_TEKSTEN', false );
 * De schakelaar in het instellingenscherm is dan grijs.
 *
 * De constante heet MM_TOOLKIT_MODULE_ plus de sleutel. Tot en met 1.3.0
 * was dat MM_TOOLKIT_ plus de sleutel, wat kan botsen met de eigen
 * constanten zoals MM_TOOLKIT_VERSIE. Die oude namen blijven werken, maar
 * alleen voor de modules die er toen al waren, zodat een nieuwe module
 * nooit op een eigen constante reageert. Staan beide er, dan wint de
 * nieuwe naam.
 */
function mm_toolkit_vastgezet( $sleutel ) {
	$oude_namen = array( 'alt_teksten', 'hero_preload', 'preview_link', 'anti_spam', 'formuliermonitor', 'ai_bots', 'rest_users' );

	$constante = 'MM_TOOLKIT_MODULE_' . strtoupper( $sleutel );
	if ( defined( $constante ) ) {
		return (bool) constant( $constante );
	}

	$constante = 'MM_TOOLKIT_' . strtoupper( $sleutel );
	if ( in_array( $sleutel, $oude_namen, true ) && defined( $constante ) ) {
		return (bool) constant( $constante );
	}

	return null;
}

/**
 * De opgeslagen keuzes, aangevuld met de standaard voor modules die
 * er later bij zijn gekomen.
 */
function mm_toolkit_keuzes() {
	$opgeslagen = get_option( MM_TOOLKIT_OPTIE, array() );
	$opgeslagen = is_array( $opgeslagen ) ? $opgeslagen : array();
	$keuzes     = array();
	foreach ( mm_toolkit_modules() as $sleutel => $module ) {
		$keuzes[ $sleutel ] = array_key_exists( $sleutel, $opgeslagen ) ? (bool) $opgeslagen[ $sleutel ] : $module['standaard'];
	}
	return $keuzes;
}

/**
 * Staat de plugin aan die deze module nodig heeft? Zonder vereiste altijd.
 *
 * @param array $module Een regel uit mm_toolkit_modules().
 */
function mm_toolkit_vereiste_actief( $module ) {
	if ( 'woocommerce' === $module['vereist'] ) {
		return mm_toolkit_woocommerce_actief();
	}
	return true;
}

/**
 * Bepaalt per module wat er gebeurt: aan, uit, of geblokkeerd omdat
 * de losse versie er nog staat of de vereiste plugin uit staat.
 */
function mm_toolkit_status() {
	static $status = null;
	if ( null !== $status ) {
		return $status;
	}
	$status = array();
	$keuzes = mm_toolkit_keuzes();
	foreach ( mm_toolkit_modules() as $sleutel => $module ) {
		$vast   = mm_toolkit_vastgezet( $sleutel );
		$gewild = null === $vast ? $keuzes[ $sleutel ] : $vast;
		$los    = mm_toolkit_losse_versie( $module );
		$kan    = mm_toolkit_vereiste_actief( $module );
		$status[ $sleutel ] = array(
			'gewild'  => $gewild,
			'vast'    => null !== $vast,
			'los'     => $los,
			'vereist' => $kan,
			'bestand' => file_exists( MM_TOOLKIT_MAP . '/modules/' . $module['bestand'] ),
			'laden'   => $gewild && $kan && '' === $los && file_exists( MM_TOOLKIT_MAP . '/modules/' . $module['bestand'] ),
		);
	}
	return $status;
}

/**
 * Draait WooCommerce op deze site?
 *
 * Bewust niet class_exists( 'WooCommerce' ): de toolkit laadt alfabetisch
 * voor WooCommerce, dus die klasse bestaat hier nog niet. De lijst met
 * actieve plugins staat wel al klaar, want daar haalt WordPress zelf uit
 * wat er geladen moet worden. Op een multisite kan WooCommerce ook voor
 * het hele netwerk aanstaan, dat staat in een aparte optie.
 */
function mm_toolkit_woocommerce_actief() {

	$bestand = 'woocommerce/woocommerce.php';

	if ( in_array( $bestand, (array) get_option( 'active_plugins', array() ), true ) ) {
		return true;
	}

	if ( is_multisite() && array_key_exists( $bestand, (array) get_site_option( 'active_sitewide_plugins', array() ) ) ) {
		return true;
	}

	return false;
}

/**
 * Oude standaardwaardes vastleggen.
 *
 * Een site die de toolkit eerder activeerde heeft geen opgeslagen keuze voor
 * modules die later zijn bijgekomen, en liep dus op de standaard van toen.
 * Wordt die standaard later omgezet, dan zou de module stilletjes omslaan.
 * Waar dat niet de bedoeling is, komt de oude standaard hier eenmalig in de
 * opgeslagen keuzes te staan.
 *
 * rest_users: kwam in 1.1.0 en stond toen standaard aan. Vanaf nu staat hij
 * standaard uit, want hij is alleen voor webshops bedoeld. Daarom wordt de
 * oude standaard alleen vastgelegd als WooCommerce aanstaat: zo houdt een
 * webshop de module aan. Op een site zonder WooCommerce blijft de sleutel
 * juist leeg, zodat die site op de nieuwe standaard uitkomt en ASE met
 * "Disable REST API" weer de route is.
 *
 * Valt er niets vast te leggen, dan schrijft deze functie ook niets, dus
 * blijft het bij het uitlezen van de optie.
 *
 * Bestaat de optie nog niet, dan is het een nieuwe site: die wordt bij het
 * activeren gevuld met de huidige standaardwaardes en hier overgeslagen.
 */
function mm_toolkit_oude_standaarden() {

	$opgeslagen = get_option( MM_TOOLKIT_OPTIE, false );
	if ( ! is_array( $opgeslagen ) ) {
		return;
	}

	$vastleggen = array();

	if ( ! array_key_exists( 'rest_users', $opgeslagen ) && mm_toolkit_woocommerce_actief() ) {
		$vastleggen['rest_users'] = true;
	}

	if ( ! $vastleggen ) {
		return;
	}

	update_option( MM_TOOLKIT_OPTIE, array_merge( $opgeslagen, $vastleggen ), false );
}

mm_toolkit_oude_standaarden();

// Modules laden. Bewust op het hoogste niveau van het bestand en niet
// binnen een functie, zodat variabelen in de modules globaal blijven.
foreach ( mm_toolkit_status() as $mm_toolkit_sleutel => $mm_toolkit_s ) {
	if ( $mm_toolkit_s['laden'] ) {
		$mm_toolkit_m = mm_toolkit_modules();
		require_once MM_TOOLKIT_MAP . '/modules/' . $mm_toolkit_m[ $mm_toolkit_sleutel ]['bestand'];
	}
}
unset( $mm_toolkit_sleutel, $mm_toolkit_s, $mm_toolkit_m );


/* -------------------------------------------------------------------------
 * Activeren
 *
 * Bij de eerste activering neemt de toolkit over wat er al draaide: staat
 * een losse versie van een module op de site, dan komt die module aan,
 * ook als hij standaard uit staat. Zo valt bijvoorbeeld de
 * Formuliermonitor niet stil bij het overstappen.
 * ---------------------------------------------------------------------- */

register_activation_hook( __FILE__, 'mm_toolkit_activeren' );

function mm_toolkit_activeren() {
	delete_transient( 'mm_toolkit_release' );
	mm_toolkit_preview_cache_bijwerken( mm_toolkit_preview_cache_gewenst() );
	mm_toolkit_aibots_cache_bijwerken( mm_toolkit_aibots_cache_gewenst() );
	if ( false !== get_option( MM_TOOLKIT_OPTIE, false ) ) {
		return;
	}
	$keuzes = array();
	foreach ( mm_toolkit_modules() as $sleutel => $module ) {
		$keuzes[ $sleutel ] = $module['standaard'] || '' !== mm_toolkit_losse_versie( $module );
	}
	add_option( MM_TOOLKIT_OPTIE, $keuzes, '', false );
}

register_deactivation_hook( __FILE__, 'mm_toolkit_deactiveren' );

/**
 * Ruimt alleen op wat zonder de plugin blijft doorlopen: de cron-events en
 * de blokken in .htaccess. Instellingen en logs blijven staan tot de plugin
 * wordt verwijderd, zie uninstall.php.
 */
function mm_toolkit_deactiveren() {
	mm_toolkit_preview_cache_bijwerken( false );
	mm_toolkit_aibots_cache_bijwerken( false );
	wp_clear_scheduled_hook( 'mm_aibots_opschonen' );
	wp_clear_scheduled_hook( 'mediamora_antispam_report' );
	wp_clear_scheduled_hook( 'mediamora_antispam_nearmiss_alert' );
}


/* -------------------------------------------------------------------------
 * Instellingenscherm
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'mm_toolkit_menu' );

function mm_toolkit_menu() {
	add_options_page( 'Mediamora Toolkit', 'Mediamora Toolkit', 'manage_options', MM_TOOLKIT_SLUG, 'mm_toolkit_scherm' );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'mm_toolkit_actielink' );

function mm_toolkit_actielink( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . MM_TOOLKIT_SLUG ) ) . '">Instellingen</a>' );
	return $links;
}

add_action( 'admin_post_mm_toolkit_opslaan', 'mm_toolkit_opslaan' );

function mm_toolkit_opslaan() {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Geen toegang.' );
	}
	check_admin_referer( 'mm_toolkit_opslaan' );

	$oud        = mm_toolkit_keuzes();
	$nieuw      = array();
	$opslaan    = array();
	$opgeslagen = get_option( MM_TOOLKIT_OPTIE, array() );
	$opgeslagen = is_array( $opgeslagen ) ? $opgeslagen : array();
	$gekozen    = isset( $_POST['mm_module'] ) ? (array) wp_unslash( $_POST['mm_module'] ) : array();

	foreach ( mm_toolkit_modules() as $sleutel => $module ) {
		// Een vastgezette module heeft een grijze schakelaar, en die stuurt
		// de browser niet mee. Dan blijft staan wat er al was; stond er nog
		// niets, dan blijft dat zo en geldt de standaard. Hetzelfde voor een
		// module waarvan de vereiste plugin even uit staat, zodat hij weer
		// aan is zodra die plugin terugkomt.
		if ( null !== mm_toolkit_vastgezet( $sleutel ) || ! mm_toolkit_vereiste_actief( $module ) ) {
			$nieuw[ $sleutel ] = $oud[ $sleutel ];
			if ( array_key_exists( $sleutel, $opgeslagen ) ) {
				$opslaan[ $sleutel ] = (bool) $opgeslagen[ $sleutel ];
			}
			continue;
		}
		$nieuw[ $sleutel ]   = isset( $gekozen[ $sleutel ] );
		$opslaan[ $sleutel ] = $nieuw[ $sleutel ];
	}

	update_option( MM_TOOLKIT_OPTIE, $opslaan, false );

	// Opruimen bij uitzetten.
	if ( $oud['preview_link'] && ! $nieuw['preview_link'] ) {
		delete_option( 'mm_preview_key' );
	}
	if ( $oud['ai_bots'] && ! $nieuw['ai_bots'] ) {
		wp_clear_scheduled_hook( 'mm_aibots_opschonen' );
	}
	if ( $oud['anti_spam'] && ! $nieuw['anti_spam'] ) {
		wp_clear_scheduled_hook( 'mediamora_antispam_report' );
		wp_clear_scheduled_hook( 'mediamora_antispam_nearmiss_alert' );
	}

	// mm_toolkit_status() is al berekend met de oude keuzes, dus hier zelf
	// uitrekenen of de preview-module na het opslaan laadt.
	$preview     = mm_toolkit_status()['preview_link'];
	$vast        = mm_toolkit_vastgezet( 'preview_link' );
	$preview_aan = ( null === $vast ? $nieuw['preview_link'] : $vast ) && '' === $preview['los'] && $preview['bestand'];
	mm_toolkit_preview_cache_bijwerken( mm_toolkit_preview_cache_gewenst( $preview_aan ) );

	// Idem voor AI-bots. Gaat de module net aan, dan zet het zelfherstel het
	// blok op de pagina waar we zo naartoe sturen.
	$aibots     = mm_toolkit_status()['ai_bots'];
	$vast       = mm_toolkit_vastgezet( 'ai_bots' );
	$aibots_aan = ( null === $vast ? $nieuw['ai_bots'] : $vast ) && '' === $aibots['los'] && $aibots['bestand'];
	mm_toolkit_aibots_cache_bijwerken( mm_toolkit_aibots_cache_gewenst( $aibots_aan ) );

	wp_safe_redirect( add_query_arg( 'opgeslagen', '1', admin_url( 'options-general.php?page=' . MM_TOOLKIT_SLUG ) ) );
	exit;
}

function mm_toolkit_scherm() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$status  = mm_toolkit_status();
	$modules = mm_toolkit_modules();

	echo '<div class="wrap"><h1>Mediamora Toolkit <span style="font-size:13px;font-weight:400;color:#646970;">versie ' . esc_html( mm_toolkit_huidige_versie() ) . '</span></h1>';

	if ( ! empty( $_GET['opgeslagen'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Opgeslagen.</p></div>';
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="mm_toolkit_opslaan">';
	wp_nonce_field( 'mm_toolkit_opslaan' );

	echo '<table class="widefat striped" style="max-width:900px;margin-top:16px;"><thead><tr><th style="width:40px;">Aan</th><th>Module</th><th style="width:260px;">Status</th></tr></thead><tbody>';

	foreach ( $modules as $sleutel => $module ) {

		$s = $status[ $sleutel ];

		if ( ! $s['bestand'] ) {
			$tekst = '<span style="color:#b32d2e;">Modulebestand ontbreekt</span>';
		} elseif ( ! $s['vereist'] && $s['gewild'] ) {
			$tekst = '<span style="color:#b32d2e;">Staat uit: WooCommerce is niet actief</span>';
		} elseif ( ! $s['vereist'] ) {
			$tekst = 'Uit, alleen aan te zetten als WooCommerce actief is';
		} elseif ( $s['los'] && $s['gewild'] ) {
			$tekst = '<span style="color:#b32d2e;">Staat uit: eerst ' . esc_html( $s['los'] ) . ' verwijderen</span>';
		} elseif ( $s['los'] ) {
			$tekst = 'Uit, ' . esc_html( $s['los'] ) . ' draait nog los';
		} elseif ( $s['laden'] ) {
			$tekst = '<span style="color:#007017;">Actief</span>';
		} else {
			$tekst = 'Uit';
		}
		if ( $s['vast'] ) {
			$tekst .= '<br><span style="color:#646970;">Vastgezet in wp-config.php</span>';
		}
		if ( 'preview_link' === $sleutel && get_transient( 'mm_toolkit_preview_htaccess_fout' ) ) {
			$tekst .= '<br><span style="color:#b32d2e;">Kon .htaccess niet bijwerken: LiteSpeed toont op gecachete pagina\'s nog de onderhoudspagina</span>';
		}
		if ( 'ai_bots' === $sleutel && get_transient( 'mm_toolkit_aibots_htaccess_fout' ) ) {
			$tekst .= '<br><span style="color:#b32d2e;">Kon .htaccess niet bijwerken: AI-crawlers krijgen nog gecachete pagina\'s</span>';
		}
		if ( 'withdrawal_waiver' === $sleutel && $s['laden'] && function_exists( 'mm_herroeping_meldingen' ) ) {
			foreach ( mm_herroeping_meldingen() as $melding ) {
				$tekst .= '<br><span style="color:#b32d2e;">' . esc_html( $melding ) . '</span>';
			}
		}

		$link = ( $module['scherm'] && $s['laden'] ) ? ' <a href="' . esc_url( admin_url( $module['scherm'] ) ) . '">Instellingen</a>' : '';
		if ( 'formuliermonitor' === $sleutel && $s['laden'] && function_exists( 'mm_monitor_test_url' ) ) {
			$link .= ' <a href="' . esc_url( mm_monitor_test_url() ) . '">Testmelding sturen</a>';
		}

		echo '<tr>';
		echo '<td><input type="checkbox" name="mm_module[' . esc_attr( $sleutel ) . ']" value="1"' . checked( $s['gewild'], true, false ) . disabled( $s['vast'] || ! $s['vereist'], true, false ) . '></td>';
		echo '<td><strong>' . esc_html( $module['naam'] ) . '</strong>' . $link . '<br><span style="color:#646970;">' . esc_html( $module['uitleg'] ) . '</span></td>';
		echo '<td>' . $tekst . '</td>';
		echo '</tr>';
	}

	echo '</tbody></table>';
	submit_button( 'Opslaan' );
	echo '</form></div>';
}

/**
 * Melding als een module aan moet staan, maar geblokkeerd wordt door
 * een losse versie. Alleen voor beheerders.
 */
add_action( 'admin_notices', 'mm_toolkit_melding' );

function mm_toolkit_melding() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$geblokkeerd = array();
	$modules     = mm_toolkit_modules();
	foreach ( mm_toolkit_status() as $sleutel => $s ) {
		if ( $s['gewild'] && $s['los'] ) {
			$geblokkeerd[] = $modules[ $sleutel ]['naam'] . ' (' . $s['los'] . ')';
		}
	}
	if ( ! $geblokkeerd ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>Mediamora Toolkit:</strong> deze modules staan aan maar wachten tot de losse versie weg is: ' . esc_html( implode( ', ', $geblokkeerd ) ) . '. Tot die tijd blijft de losse versie gewoon werken.</p></div>';
}


/* -------------------------------------------------------------------------
 * Preview-link: LiteSpeed-cache overslaan
 *
 * LiteSpeed serveert een gecachete pagina voordat PHP draait. DONOTCACHEPAGE
 * en litespeed_control_set_nocache in de module komen dan te laat, en een
 * bezoeker met een geldig preview-cookie krijgt toch de onderhoudspagina.
 * Daarom staat er bovenaan .htaccess een blok dat LiteSpeed voor verzoeken
 * met het cookie mm_preview de cache laat overslaan.
 *
 * Het blok staat er alleen zolang de module laadt en de onderhoudsmodus van
 * Elementor aanstaat. Na de lancering slaan klanten met een oud cookie de
 * cache dus niet meer over.
 *
 * Staat bewust hier en niet in de module: een module die uit staat laadt
 * niet en kan zijn eigen blok dan niet meer opruimen.
 *
 * De server controleert alleen of het cookie er is, niet of het klopt. Wie
 * zelf een mm_preview-cookie zet, krijgt de onderhoudspagina ongecachet.
 * Dat kost wat serverwerk en lekt niets.
 * ---------------------------------------------------------------------- */

// De marker, het blok en het schrijven staan in includes/htaccess.php,
// zodat uninstall.php ze ook kan gebruiken.

/**
 * Hoort het blok in .htaccess te staan?
 *
 * @param bool|null   $module_aan Laadt de preview-module? Null: huidige status.
 * @param string|null $modus      Stand van de onderhoudsmodus. Null: uit de optie.
 */
function mm_toolkit_preview_cache_gewenst( $module_aan = null, $modus = null ) {
	if ( null === $module_aan ) {
		$status     = mm_toolkit_status();
		$module_aan = $status['preview_link']['laden'];
	}
	if ( null === $modus ) {
		// Alleen aangeroepen in wp-admin, bij activeren en via WP-CLI. Daar laat
		// de module deze optie met rust; alleen op de voorkant maakt hij hem
		// leeg voor preview-bezoekers.
		$modus = (string) get_option( 'elementor_maintenance_mode_mode', '' );
	}
	return $module_aan && in_array( $modus, array( 'maintenance', 'coming_soon' ), true );
}

// Onderhoudsmodus van Elementor aan- of uitgezet.
add_action( 'update_option_elementor_maintenance_mode_mode', 'mm_toolkit_preview_modus_gewijzigd', 10, 2 );
add_action( 'add_option_elementor_maintenance_mode_mode', 'mm_toolkit_preview_modus_toegevoegd', 10, 2 );
add_action( 'delete_option_elementor_maintenance_mode_mode', 'mm_toolkit_preview_modus_verwijderd' );

function mm_toolkit_preview_modus_gewijzigd( $oud, $nieuw ) {
	delete_transient( 'mm_toolkit_preview_htaccess_fout' );
	mm_toolkit_preview_cache_bijwerken( mm_toolkit_preview_cache_gewenst( null, (string) $nieuw ) );
}

function mm_toolkit_preview_modus_toegevoegd( $optie, $waarde ) {
	mm_toolkit_preview_modus_gewijzigd( '', $waarde );
}

function mm_toolkit_preview_modus_verwijderd() {
	mm_toolkit_preview_cache_bijwerken( false );
}

/**
 * Zelfherstel: bij elke beheerpagina nagaan of het blok klopt met de
 * gewenste stand. Vangt een wp-config-define, een losse versie die erbij
 * komt, een modus die via WP-CLI is gezet of een host die .htaccess heeft
 * overschreven. Kost één keer .htaccess lezen; schrijven gebeurt alleen bij
 * een verschil. Na een mislukte poging een dag rust.
 */
add_action( 'admin_init', 'mm_toolkit_preview_cache_herstel' );

function mm_toolkit_preview_cache_herstel() {
	if ( wp_doing_ajax() || null === mm_toolkit_litespeed_server() ) {
		return;
	}
	// Op het instellingenscherm altijd opnieuw proberen, zodat een opgeloste
	// schrijfrechtenkwestie meteen zichtbaar wordt.
	$scherm = isset( $_GET['page'] ) && MM_TOOLKIT_SLUG === $_GET['page'];
	if ( ! $scherm && get_transient( 'mm_toolkit_preview_htaccess_fout' ) ) {
		return;
	}
	mm_toolkit_preview_cache_bijwerken( mm_toolkit_preview_cache_gewenst() );
}


/* -------------------------------------------------------------------------
 * AI-bots: LiteSpeed-cache overslaan
 *
 * Een crawler die een gecachete pagina krijgt, bereikt PHP nooit en wordt
 * dus niet geteld. Op LiteSpeed-sites tellen de AI-bots daardoor structureel
 * te laag. Met de instelling "AI-crawlers buiten de cache houden" op het
 * AI-bots-scherm komt er bovenaan .htaccess een blok dat LiteSpeed voor de
 * user agents van de module de cache laat overslaan.
 *
 * Googlebot en Bingbot staan er niet in: dat zijn alleen ijkpunten, en ze
 * buiten de cache houden kost veel serverwerk. Hun aantallen blijven dus te
 * laag. Een CDN vóór de server (QUIC.cloud, Cloudflare met paginacache) kan
 * een crawler nog steeds een kopie geven; daar helpt dit blok niet.
 *
 * Staat hier en niet in de module, om dezelfde reden als bij de preview: een
 * module die uit staat laadt niet en kan zijn blok dan niet opruimen. Zet
 * iemand de module uit, dan blijft de instelling bewaard en komt het blok
 * terug zodra de module weer aan gaat.
 * ---------------------------------------------------------------------- */

const MM_TOOLKIT_AIBOTS_CACHE_OPTIE = 'mm_toolkit_aibots_cache';

/**
 * Hoort het AI-bots-blok in .htaccess te staan?
 *
 * @param bool|null $module_aan Laadt de AI-bots-module? Null: huidige status.
 */
function mm_toolkit_aibots_cache_gewenst( $module_aan = null ) {
	if ( null === $module_aan ) {
		$status     = mm_toolkit_status();
		$module_aan = $status['ai_bots']['laden'];
	}
	return $module_aan && (bool) get_option( MM_TOOLKIT_AIBOTS_CACHE_OPTIE, false );
}

/**
 * Zelfherstel, zoals bij de preview. Op het toolkitscherm en het
 * AI-bots-scherm altijd opnieuw proberen.
 */
add_action( 'admin_init', 'mm_toolkit_aibots_cache_herstel' );

function mm_toolkit_aibots_cache_herstel() {
	if ( wp_doing_ajax() || null === mm_toolkit_litespeed_server() ) {
		return;
	}
	$scherm = isset( $_GET['page'] ) && in_array( $_GET['page'], array( MM_TOOLKIT_SLUG, 'mm-aibots' ), true );
	if ( ! $scherm && get_transient( 'mm_toolkit_aibots_htaccess_fout' ) ) {
		return;
	}
	mm_toolkit_aibots_cache_bijwerken( mm_toolkit_aibots_cache_gewenst() );
}


/* -------------------------------------------------------------------------
 * Updates via GitHub
 *
 * Zelfde kale updater als in de Formuliermonitor: geen plugin-update-checker,
 * want die bibliotheek past niet door de webuploader van GitHub.
 * Releases taggen als v1.0.0, v1.0.1 enzovoort.
 *
 * Ophalen bij GitHub kan tot tien seconden duren en gebeurt daarom alleen
 * in de beheeromgeving, in WP-cron, via WP-CLI en wanneer WordPress zelf op
 * updates controleert (dan loopt er toch al een verzoek naar wordpress.org,
 * ook als MainWP die controle vanaf de voorkant start). Elders geldt de
 * laatst opgehaalde release. Die staat los van de transient in een optie
 * die niet verloopt, zodat een update niet uit de lijst verdwijnt als
 * GitHub even niet antwoordt of de transient verlopen is.
 * ---------------------------------------------------------------------- */

/**
 * De geïnstalleerde versie, uit de kop van dit bestand. Dezelfde bron als
 * WordPress zelf gebruikt, dus er is maar één plek die moet kloppen.
 */
function mm_toolkit_huidige_versie() {
	$kop = get_file_data( MM_TOOLKIT_BESTAND, array( 'Version' => 'Version' ), 'plugin' );
	return ! empty( $kop['Version'] ) ? $kop['Version'] : MM_TOOLKIT_VERSIE;
}

/**
 * Mag dit verzoek GitHub aanroepen?
 */
function mm_toolkit_mag_ophalen() {
	return is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI );
}

/**
 * De laatst goed opgehaalde release, of een lege array.
 */
function mm_toolkit_bewaarde_release() {
	$bewaard = get_option( 'mm_toolkit_release_laatst', array() );
	return is_array( $bewaard ) ? $bewaard : array();
}

/**
 * @param bool|null $ophalen Mag GitHub aangeroepen worden? Null: afhankelijk
 *                           van het soort verzoek.
 */
function mm_toolkit_laatste_release( $ophalen = null ) {

	// De transient bepaalt alleen wanneer er opnieuw wordt opgehaald. Een
	// lege array betekent: vorige poging mislukt, nog even niet opnieuw.
	$cache = get_transient( 'mm_toolkit_release' );
	if ( false !== $cache ) {
		return is_array( $cache ) && $cache ? $cache : mm_toolkit_bewaarde_release();
	}

	if ( null === $ophalen ) {
		$ophalen = mm_toolkit_mag_ophalen();
	}
	if ( ! $ophalen ) {
		return mm_toolkit_bewaarde_release();
	}

	$antwoord = wp_remote_get(
		'https://api.github.com/repos/' . MM_TOOLKIT_REPO . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => MM_TOOLKIT_SLUG,
			),
		)
	);

	if ( is_wp_error( $antwoord ) || 200 !== wp_remote_retrieve_response_code( $antwoord ) ) {
		set_transient( 'mm_toolkit_release', array(), 2 * HOUR_IN_SECONDS );
		return mm_toolkit_bewaarde_release();
	}

	$data = json_decode( wp_remote_retrieve_body( $antwoord ), true );

	if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
		set_transient( 'mm_toolkit_release', array(), 2 * HOUR_IN_SECONDS );
		return mm_toolkit_bewaarde_release();
	}

	$release = array(
		'versie'    => ltrim( (string) $data['tag_name'], 'vV' ),
		'zip'       => ! empty( $data['zipball_url'] ) && mm_toolkit_pakket_toegestaan( $data['zipball_url'] ) ? $data['zipball_url'] : '',
		'url'       => ! empty( $data['html_url'] ) ? $data['html_url'] : '',
		'datum'     => ! empty( $data['published_at'] ) ? $data['published_at'] : '',
		'changelog' => ! empty( $data['body'] ) ? $data['body'] : '',
	);

	set_transient( 'mm_toolkit_release', $release, 12 * HOUR_IN_SECONDS );
	update_option( 'mm_toolkit_release_laatst', $release, false );

	return $release;
}

/**
 * Komt het updatepakket echt van GitHub en van deze repo?
 *
 * GitHub geeft de zip als api.github.com/repos/<repo>/zipball/<tag> en
 * stuurt die door naar codeload.github.com/<repo>/... Andere hosts, andere
 * repo's of geen https: geen update aanbieden. Repo-namen zijn bij GitHub
 * hoofdletterongevoelig.
 */
function mm_toolkit_pakket_toegestaan( $url ) {

	$delen = wp_parse_url( (string) $url );

	if ( empty( $delen['scheme'] ) || 'https' !== strtolower( $delen['scheme'] ) || empty( $delen['host'] ) || empty( $delen['path'] ) ) {
		return false;
	}
	if ( isset( $delen['port'] ) || isset( $delen['user'] ) || isset( $delen['pass'] ) ) {
		return false;
	}

	$host = strtolower( $delen['host'] );
	$pad  = strtolower( rawurldecode( $delen['path'] ) );
	$repo = strtolower( MM_TOOLKIT_REPO );

	// Geen ../ om via het juiste voorvoegsel toch bij een andere repo uit te komen.
	if ( false !== strpos( $pad, '..' ) ) {
		return false;
	}

	if ( 'api.github.com' === $host ) {
		return 0 === strpos( $pad, '/repos/' . $repo . '/zipball/' );
	}
	if ( 'codeload.github.com' === $host ) {
		return 0 === strpos( $pad, '/' . $repo . '/' );
	}

	return false;
}

add_filter( 'site_transient_update_plugins', 'mm_toolkit_meld_update' );
add_filter( 'pre_set_site_transient_update_plugins', 'mm_toolkit_meld_update_bij_controle' );

/**
 * WordPress controleert zelf op updates en slaat de uitkomst op. Hier mag
 * altijd opgehaald worden.
 */
function mm_toolkit_meld_update_bij_controle( $transient ) {
	return mm_toolkit_meld_update( $transient, true );
}

/**
 * @param object    $transient De transient update_plugins.
 * @param bool|null $ophalen   Zie mm_toolkit_laatste_release().
 */
function mm_toolkit_meld_update( $transient, $ophalen = null ) {

	if ( ! is_object( $transient ) ) {
		return $transient;
	}

	$release = mm_toolkit_laatste_release( $ophalen );
	// Ook hier controleren: een release in de cache van vóór deze controle
	// kan nog een ongecontroleerd pakket bevatten.
	if ( empty( $release['versie'] ) || empty( $release['zip'] ) || ! mm_toolkit_pakket_toegestaan( $release['zip'] ) ) {
		return $transient;
	}

	$bestand = plugin_basename( MM_TOOLKIT_BESTAND );
	$info    = (object) array(
		'id'          => MM_TOOLKIT_REPO,
		'slug'        => MM_TOOLKIT_SLUG,
		'plugin'      => $bestand,
		'new_version' => $release['versie'],
		'url'         => $release['url'],
		'package'     => $release['zip'],
		'icons'       => array(),
		'banners'     => array(),
		'tested'      => get_bloginfo( 'version' ),
	);

	// De update staat nu ook in de opgeslagen transient. Daarom de vermelding
	// uit de andere lijst weghalen, anders blijft na het bijwerken een oude
	// "update beschikbaar" staan.
	if ( version_compare( $release['versie'], mm_toolkit_huidige_versie(), '>' ) ) {
		$transient->response[ $bestand ] = $info;
		unset( $transient->no_update[ $bestand ] );
	} else {
		$transient->no_update[ $bestand ] = $info;
		unset( $transient->response[ $bestand ] );
	}

	return $transient;
}

add_filter( 'plugins_api', 'mm_toolkit_plugin_details', 10, 3 );

function mm_toolkit_plugin_details( $resultaat, $actie, $args ) {

	if ( 'plugin_information' !== $actie || empty( $args->slug ) || MM_TOOLKIT_SLUG !== $args->slug ) {
		return $resultaat;
	}

	$release = mm_toolkit_laatste_release();

	return (object) array(
		'name'          => 'Mediamora Toolkit',
		'slug'          => MM_TOOLKIT_SLUG,
		'version'       => ! empty( $release['versie'] ) ? $release['versie'] : mm_toolkit_huidige_versie(),
		'author'        => '<a href="https://mediamora.nl">Mediamora</a>',
		'homepage'      => 'https://github.com/' . MM_TOOLKIT_REPO,
		'download_link' => ! empty( $release['zip'] ) && mm_toolkit_pakket_toegestaan( $release['zip'] ) ? $release['zip'] : '',
		'last_updated'  => ! empty( $release['datum'] ) ? $release['datum'] : '',
		'sections'      => array(
			'description' => 'De vaste Mediamora-onderdelen in één plugin.',
			'changelog'   => ! empty( $release['changelog'] ) ? nl2br( esc_html( $release['changelog'] ) ) : 'Geen changelog.',
		),
	);
}

/**
 * GitHub levert een zip die uitpakt als "repo-commithash". Hier wordt de
 * map teruggezet naar de vaste slug, anders raakt de updatekoppeling kwijt.
 */
add_filter( 'upgrader_source_selection', 'mm_toolkit_herstel_mapnaam', 10, 4 );

function mm_toolkit_herstel_mapnaam( $bron, $externe_bron, $upgrader, $extra = null ) {

	if ( empty( $extra['plugin'] ) || plugin_basename( MM_TOOLKIT_BESTAND ) !== $extra['plugin'] ) {
		return $bron;
	}

	global $wp_filesystem;

	$gewenst = trailingslashit( $externe_bron ) . MM_TOOLKIT_SLUG;

	if ( trailingslashit( $bron ) === trailingslashit( $gewenst ) ) {
		return $bron;
	}

	if ( ! $wp_filesystem ) {
		return $bron;
	}

	// Een map van een afgebroken update kan de verplaatsing laten mislukken.
	// move() met overwrite haalt een bestaande map niet in elke versie van
	// WordPress weg, dus zelf opruimen. Alleen binnen de werkmap van de
	// upgrader, nooit in de pluginmap.
	$upgrade = trailingslashit( $wp_filesystem->wp_content_dir() ) . 'upgrade/';
	if ( 0 === strpos( $gewenst, $upgrade ) && $wp_filesystem->exists( $gewenst ) ) {
		$wp_filesystem->delete( $gewenst, true );
	}

	if ( $wp_filesystem->move( $bron, $gewenst, true ) ) {
		return trailingslashit( $gewenst );
	}

	return $bron;
}

add_action( 'upgrader_process_complete', 'mm_toolkit_leeg_cache' );

function mm_toolkit_leeg_cache() {
	delete_transient( 'mm_toolkit_release' );
}
