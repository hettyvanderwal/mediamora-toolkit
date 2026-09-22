<?php
/**
 * Plugin Name:       Mediamora Toolkit
 * Plugin URI:        https://github.com/hettyvanderwal/mediamora-toolkit
 * Description:       De vaste Mediamora-onderdelen in één plugin, per onderdeel aan en uit te zetten onder Instellingen > Mediamora Toolkit.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Mediamora
 * Author URI:        https://mediamora.nl
 * License:           GPL-2.0-or-later
 * Text Domain:       mediamora-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Versienummer. Moet gelijk zijn aan "Version" in de kop hierboven
 * en aan de tag van de GitHub-release (zonder de v).
 */
const MM_TOOLKIT_VERSIE = '1.1.0';
const MM_TOOLKIT_REPO   = 'hettyvanderwal/mediamora-toolkit';
const MM_TOOLKIT_SLUG   = 'mediamora-toolkit';
const MM_TOOLKIT_OPTIE  = 'mm_toolkit_modules';

define( 'MM_TOOLKIT_BESTAND', __FILE__ );
define( 'MM_TOOLKIT_MAP', __DIR__ );


/* -------------------------------------------------------------------------
 * Modules
 *
 * losse_mu:      mu-plugin die deze module vervangt
 * losse_plugin:  gewone plugin die deze module vervangt
 * merkteken:     functie of klasse die de losse versie definieert. Laadt
 *                die al, dan blijft de module uit, anders botsen de
 *                functienamen en ligt de site plat.
 *
 * Per site vastzetten kan in wp-config.php, bijvoorbeeld:
 *   define( 'MM_TOOLKIT_ALT_TEKSTEN', false );
 * De schakelaar in het instellingenscherm is dan grijs.
 * ---------------------------------------------------------------------- */

function mm_toolkit_modules() {
	return array(
		'alt_teksten'      => array(
			'naam'         => 'Alt-teksten',
			'uitleg'       => 'Vult bij nieuwe uploads zelf een alt-tekst in, zodat Elementor geen bestandsnamen toont. Niet nodig op een academie.',
			'bestand'      => 'alt-teksten.php',
			'standaard'    => true,
			'losse_mu'     => 'mediamora-alt-teksten.php',
			'losse_plugin' => '',
			'merkteken'    => array( 'class', 'Mediamora_Alt_Teksten' ),
			'scherm'       => '',
		),
		'hero_preload'     => array(
			'naam'         => 'Hero-preload',
			'uitleg'       => 'Laat de achtergrondafbeelding of slideshow van de bovenste container vooraf laden. Scheelt vooral op mobiel op de LCP.',
			'bestand'      => 'hero-preload.php',
			'standaard'    => true,
			'losse_mu'     => 'mediamora-hero-preload.php',
			'losse_plugin' => '',
			'merkteken'    => array(),
			'scherm'       => '',
		),
		'preview_link'     => array(
			'naam'         => 'Preview-link',
			'uitleg'       => 'Klanten kijken mee op een site in onderhoudsmodus via een link, zonder inloggen. Doet niets zodra de onderhoudsmodus uit staat. Uitzetten maakt alle bestaande links ongeldig.',
			'bestand'      => 'preview-link.php',
			'standaard'    => true,
			'losse_mu'     => 'mm-preview.php',
			'losse_plugin' => '',
			'merkteken'    => array( 'function', 'mm_preview_key' ),
			'scherm'       => '',
		),
		'anti_spam'        => array(
			'naam'         => 'Anti-spam',
			'uitleg'       => 'Weigert spam via Elementor Pro-formulieren. Werkt alleen als Elementor Pro actief is.',
			'bestand'      => 'anti-spam.php',
			'standaard'    => true,
			'losse_mu'     => '',
			'losse_plugin' => 'mediamora-anti-spam-elementor/mediamora-anti-spam-elementor.php',
			'merkteken'    => array( 'function', 'mediamora_antispam_validate_form' ),
			'scherm'       => 'options-general.php?page=mediamora-antispam',
		),
		'formuliermonitor' => array(
			'naam'         => 'Formuliermonitor',
			'uitleg'       => 'Stuurt Mediamora een melding zodra de site een e-mail niet kan versturen. Hoort bij het onderhoudspakket.',
			'bestand'      => 'formuliermonitor.php',
			'standaard'    => false,
			'losse_mu'     => '',
			'losse_plugin' => 'mediamora-formuliermonitor/mediamora-formuliermonitor.php',
			'merkteken'    => array( 'function', 'mm_monitor_verwerk_fout' ),
			'scherm'       => '',
		),
		'ai_bots'          => array(
			'naam'         => 'AI-bots',
			'uitleg'       => 'Houdt per maand bij welke AI-crawlers de site bezoeken. Hoort bij de GEO-check.',
			'bestand'      => 'ai-bots.php',
			'standaard'    => false,
			'losse_mu'     => 'mediamora-ai-bots.php',
			'losse_plugin' => '',
			'merkteken'    => array( 'function', 'mm_aibots_lijst' ),
			'scherm'       => 'tools.php?page=mm-aibots',
		),
		'rest_users'       => array(
			'naam'         => 'REST-gebruikers afschermen',
			'uitleg'       => 'Sluit /wp-json/wp/v2/users af voor bezoekers die niet zijn ingelogd, zodat inlognamen niet uit te lezen zijn. De rest van de REST API blijft werken, dus ook de webhooks van Mollie en MyParcel. Gebruik dit op een webshop in plaats van "Disable REST API" in ASE.',
			'bestand'      => 'rest-users.php',
			'standaard'    => true,
			'losse_mu'     => 'mediamora-rest-users.php',
			'losse_plugin' => '',
			'merkteken'    => array( 'function', 'mm_rest_users_afschermen' ),
			'scherm'       => '',
		),
	);
}

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
 */
function mm_toolkit_vastgezet( $sleutel ) {
	$constante = 'MM_TOOLKIT_' . strtoupper( $sleutel );
	return defined( $constante ) ? (bool) constant( $constante ) : null;
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
 * Bepaalt per module wat er gebeurt: aan, uit, of geblokkeerd omdat
 * de losse versie er nog staat.
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
		$status[ $sleutel ] = array(
			'gewild'  => $gewild,
			'vast'    => null !== $vast,
			'los'     => $los,
			'bestand' => file_exists( MM_TOOLKIT_MAP . '/modules/' . $module['bestand'] ),
			'laden'   => $gewild && '' === $los && file_exists( MM_TOOLKIT_MAP . '/modules/' . $module['bestand'] ),
		);
	}
	return $status;
}

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
	if ( false !== get_option( MM_TOOLKIT_OPTIE, false ) ) {
		return;
	}
	$keuzes = array();
	foreach ( mm_toolkit_modules() as $sleutel => $module ) {
		$keuzes[ $sleutel ] = $module['standaard'] || '' !== mm_toolkit_losse_versie( $module );
	}
	add_option( MM_TOOLKIT_OPTIE, $keuzes, '', false );
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

	$oud    = mm_toolkit_keuzes();
	$nieuw  = array();
	$gekozen = isset( $_POST['mm_module'] ) ? (array) wp_unslash( $_POST['mm_module'] ) : array();

	foreach ( mm_toolkit_modules() as $sleutel => $module ) {
		$nieuw[ $sleutel ] = isset( $gekozen[ $sleutel ] );
	}

	update_option( MM_TOOLKIT_OPTIE, $nieuw, false );

	// Opruimen bij uitzetten.
	if ( $oud['preview_link'] && ! $nieuw['preview_link'] ) {
		delete_option( 'mm_preview_key' );
	}
	if ( $oud['ai_bots'] && ! $nieuw['ai_bots'] ) {
		wp_clear_scheduled_hook( 'mm_aibots_opschonen' );
	}

	wp_safe_redirect( add_query_arg( 'opgeslagen', '1', admin_url( 'options-general.php?page=' . MM_TOOLKIT_SLUG ) ) );
	exit;
}

function mm_toolkit_scherm() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$status  = mm_toolkit_status();
	$modules = mm_toolkit_modules();

	echo '<div class="wrap"><h1>Mediamora Toolkit <span style="font-size:13px;font-weight:400;color:#646970;">versie ' . esc_html( MM_TOOLKIT_VERSIE ) . '</span></h1>';

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

		$link = ( $module['scherm'] && $s['laden'] ) ? ' <a href="' . esc_url( admin_url( $module['scherm'] ) ) . '">Instellingen</a>' : '';

		echo '<tr>';
		echo '<td><input type="checkbox" name="mm_module[' . esc_attr( $sleutel ) . ']" value="1"' . checked( $s['gewild'], true, false ) . disabled( $s['vast'], true, false ) . '></td>';
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
 * Updates via GitHub
 *
 * Zelfde kale updater als in de Formuliermonitor: geen plugin-update-checker,
 * want die bibliotheek past niet door de webuploader van GitHub.
 * Releases taggen als v1.0.0, v1.0.1 enzovoort.
 * ---------------------------------------------------------------------- */

function mm_toolkit_laatste_release() {

	$cache = get_transient( 'mm_toolkit_release' );
	if ( false !== $cache ) {
		return is_array( $cache ) ? $cache : array();
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
		return array();
	}

	$data = json_decode( wp_remote_retrieve_body( $antwoord ), true );

	if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
		set_transient( 'mm_toolkit_release', array(), 2 * HOUR_IN_SECONDS );
		return array();
	}

	$release = array(
		'versie'    => ltrim( (string) $data['tag_name'], 'vV' ),
		'zip'       => ! empty( $data['zipball_url'] ) ? $data['zipball_url'] : '',
		'url'       => ! empty( $data['html_url'] ) ? $data['html_url'] : '',
		'datum'     => ! empty( $data['published_at'] ) ? $data['published_at'] : '',
		'changelog' => ! empty( $data['body'] ) ? $data['body'] : '',
	);

	set_transient( 'mm_toolkit_release', $release, 12 * HOUR_IN_SECONDS );

	return $release;
}

add_filter( 'site_transient_update_plugins', 'mm_toolkit_meld_update' );

function mm_toolkit_meld_update( $transient ) {

	if ( ! is_object( $transient ) ) {
		return $transient;
	}

	$release = mm_toolkit_laatste_release();
	if ( empty( $release['versie'] ) || empty( $release['zip'] ) ) {
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

	if ( version_compare( $release['versie'], MM_TOOLKIT_VERSIE, '>' ) ) {
		$transient->response[ $bestand ] = $info;
	} else {
		$transient->no_update[ $bestand ] = $info;
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
		'version'       => ! empty( $release['versie'] ) ? $release['versie'] : MM_TOOLKIT_VERSIE,
		'author'        => '<a href="https://mediamora.nl">Mediamora</a>',
		'homepage'      => 'https://github.com/' . MM_TOOLKIT_REPO,
		'download_link' => ! empty( $release['zip'] ) ? $release['zip'] : '',
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

	if ( $wp_filesystem && $wp_filesystem->move( $bron, $gewenst ) ) {
		return trailingslashit( $gewenst );
	}

	return $bron;
}

add_action( 'upgrader_process_complete', 'mm_toolkit_leeg_cache' );

function mm_toolkit_leeg_cache() {
	delete_transient( 'mm_toolkit_release' );
}
