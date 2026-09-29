<?php
/**
 * Mediamora Toolkit, de lijst met modules.
 *
 * Staat in een eigen bestand zodat ook uninstall.php het kan laden. Dat
 * draait zonder de rest van de plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * Modules
 *
 * losse_mu:      mu-plugin die deze module vervangt
 * losse_plugin:  gewone plugin die deze module vervangt
 * merkteken:     functie of klasse die de losse versie definieert. Laadt
 *                die al, dan blijft de module uit, anders botsen de
 *                functienamen en ligt de site plat.
 * vereist:       plugin die aan moet staan, anders is de module niet aan
 *                te zetten. Nu alleen 'woocommerce'.
 * ---------------------------------------------------------------------- */

function mm_toolkit_modules() {
	return array(
		'alt_teksten'       => array(
			'naam'         => 'Alt-teksten',
			'uitleg'       => 'Vult bij nieuwe uploads zelf een alt-tekst in, zodat Elementor geen bestandsnamen toont. Niet nodig op een academie.',
			'bestand'      => 'alt-teksten.php',
			'standaard'    => true,
			'losse_mu'     => 'mediamora-alt-teksten.php',
			'losse_plugin' => '',
			'merkteken'    => array( 'class', 'Mediamora_Alt_Teksten' ),
			'scherm'       => '',
			'vereist'      => '',
		),
		'hero_preload'      => array(
			'naam'         => 'Hero-preload',
			'uitleg'       => 'Laat de achtergrondafbeelding of slideshow van de bovenste container vooraf laden. Scheelt vooral op mobiel op de LCP. Werkt op pagina\'s en posts, en op winkel-, categorie-, blog- en zoekpagina\'s via het archieftemplate van Elementor Pro.',
			'bestand'      => 'hero-preload.php',
			'standaard'    => true,
			'losse_mu'     => 'mediamora-hero-preload.php',
			'losse_plugin' => '',
			'merkteken'    => array(),
			'scherm'       => '',
			'vereist'      => '',
		),
		'preview_link'      => array(
			'naam'         => 'Preview-link',
			'uitleg'       => 'Klanten kijken mee op een site in onderhoudsmodus via een link, zonder inloggen. Doet niets zodra de onderhoudsmodus uit staat. Uitzetten maakt alle bestaande links ongeldig.',
			'bestand'      => 'preview-link.php',
			'standaard'    => true,
			'losse_mu'     => 'mm-preview.php',
			'losse_plugin' => '',
			'merkteken'    => array( 'function', 'mm_preview_key' ),
			'scherm'       => '',
			'vereist'      => '',
		),
		'anti_spam'         => array(
			'naam'         => 'Anti-spam',
			'uitleg'       => 'Weigert spam via Elementor Pro-formulieren. Werkt alleen als Elementor Pro actief is.',
			'bestand'      => 'anti-spam.php',
			'standaard'    => true,
			'losse_mu'     => '',
			'losse_plugin' => 'mediamora-anti-spam-elementor/mediamora-anti-spam-elementor.php',
			'merkteken'    => array( 'function', 'mediamora_antispam_validate_form' ),
			'scherm'       => 'options-general.php?page=mediamora-antispam',
			'vereist'      => '',
		),
		'formuliermonitor'  => array(
			'naam'         => 'Formuliermonitor',
			'uitleg'       => 'Stuurt Mediamora een melding zodra de site een e-mail niet kan versturen. Hoort bij het onderhoudspakket.',
			'bestand'      => 'formuliermonitor.php',
			'standaard'    => false,
			'losse_mu'     => '',
			'losse_plugin' => 'mediamora-formuliermonitor/mediamora-formuliermonitor.php',
			'merkteken'    => array( 'function', 'mm_monitor_verwerk_fout' ),
			'scherm'       => '',
			'vereist'      => '',
		),
		'ai_bots'           => array(
			'naam'         => 'AI-bots',
			'uitleg'       => 'Houdt per maand bij welke AI-crawlers de site bezoeken. Hoort bij de GEO-check.',
			'bestand'      => 'ai-bots.php',
			'standaard'    => false,
			'losse_mu'     => 'mediamora-ai-bots.php',
			'losse_plugin' => '',
			'merkteken'    => array( 'function', 'mm_aibots_lijst' ),
			'scherm'       => 'options-general.php?page=mm-aibots',
			'vereist'      => '',
		),
		'rest_users'        => array(
			'naam'         => 'REST-gebruikers afschermen',
			'uitleg'       => 'Sluit /wp-json/wp/v2/users af voor bezoekers die niet zijn ingelogd, zodat inlognamen niet uit te lezen zijn. De rest van de REST API blijft werken. Alleen nodig op een webshop, want daar blokkeert "Disable REST API" in ASE de webhooks van Mollie en MyParcel. Op andere sites blijft ASE de route.',
			'bestand'      => 'rest-users.php',
			'standaard'    => false,
			'losse_mu'     => 'mediamora-rest-users.php',
			'losse_plugin' => '',
			'merkteken'    => array( 'function', 'mm_rest_users_afschermen' ),
			'scherm'       => '',
			'vereist'      => '',
		),
		// Geen merkteken voor de oude functions.php-code: die laadt pas na de
		// plugins. De module kijkt daar zelf naar op init.
		'withdrawal_waiver' => array(
			'naam'         => 'Herroepingsrecht bij afrekenen',
			'uitleg'       => 'Verplicht vinkje boven de bestelknop waarmee de koper instemt met directe toegang tot een online cursus en afziet van het herroepingsrecht. De tekst komt op de bestelling, in de bestelmail en in het beheer. Alleen voor webshops: zonder WooCommerce is hij niet aan te zetten. Werkt met de klassieke checkout en de Checkout-widget van Elementor, niet met het checkoutblok.',
			'bestand'      => 'withdrawal-waiver.php',
			'standaard'    => false,
			'losse_mu'     => '',
			'losse_plugin' => '',
			'merkteken'    => array(),
			'scherm'       => 'options-general.php?page=mm-herroeping',
			'vereist'      => 'woocommerce',
		),
	);
}
