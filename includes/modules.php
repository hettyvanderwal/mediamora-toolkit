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
		// Geen merkteken voor de oude functions.php-code (mm_withdrawal_text,
		// mm_consent_text): die laadt pas na de plugins. De module kijkt daar
		// zelf per soort naar op init.
		'withdrawal_waiver' => array(
			'naam'         => 'Herroepingsrecht bij afrekenen',
			'uitleg'       => 'Verplichte vinkjes boven de bestelknop: bij digitale content (zoals een online cursus) stemt de koper in met directe toegang en ziet af van het herroepingsrecht, bij een dienst (zoals coaching of een consult) stemt hij in met directe uitvoering. Per product, categorie of standaard in te stellen. De tekst komt op de bestelling, in de bestelmail en in het beheer. Alleen voor webshops: zonder WooCommerce is hij niet aan te zetten. Werkt met de klassieke checkout en de Checkout-widget van Elementor, niet met het checkoutblok.',
			'bestand'      => 'withdrawal-waiver.php',
			'standaard'    => false,
			'losse_mu'     => '',
			'losse_plugin' => '',
			'merkteken'    => array(),
			'scherm'       => 'options-general.php?page=mm-herroeping',
			'vereist'      => 'woocommerce',
		),
		// De drie modules hieronder draaiden eerst als test in functions.php,
		// als closures en dus zonder merkteken. Ze herkennen die testcode zelf.
		'popup_tik'         => array(
			'naam'         => 'Mobiel menu: vroege tik',
			'uitleg'       => 'Een tik op de hamburger voordat Elementor Pro klaar is ging verloren. Deze module onthoudt de tik en opent de popup zodra Elementor Pro zover is. Is Elementor Pro na 15 seconden nog niet klaar, dan stopt hij en gaat elke tik weer gewoon door. Doet niets zonder Elementor Pro.',
			'bestand'      => 'popup-tik.php',
			'standaard'    => true,
			'losse_mu'     => '',
			'losse_plugin' => '',
			'merkteken'    => array(),
			'scherm'       => '',
			'vereist'      => '',
		),
		'jquery_wacht'      => array(
			'naam'         => 'jQuery-wachtrem',
			'uitleg'       => 'Met jQuery uitgesteld (LiteSpeed defer) kon Elementor starten voordat Elementor Pro luisterde, waarna mobiel menu, sticky headers en Pro-formulieren die paginaweergave niet werkten. Deze module houdt jQuery.ready vast tot DOMContentLoaded, met als vangnet het load-event, zodat jQuery.ready nooit blijft hangen als het regeltje pas na DOMContentLoaded uitvoert. Doet niets als jQuery niet is uitgesteld. Gebruikt jQuery.holdReady, dat verouderd is in jQuery 3: bij een overstap naar jQuery 4 opnieuw bekijken.',
			'bestand'      => 'jquery-wacht.php',
			'standaard'    => true,
			'losse_mu'     => '',
			'losse_plugin' => '',
			'merkteken'    => array(),
			'scherm'       => '',
			'vereist'      => '',
		),
		'quic_cloud'        => array(
			'naam'         => 'QUIC.cloud en kritieke CSS',
			'uitleg'       => 'Aanzetten als de kritieke CSS van LiteSpeed via QUIC.cloud wordt ingericht. Laat QUIC.cloud terugbellen ondanks "REST API uitschakelen" in ASE, meldt het als #elementor-device-mode in de CCSS-allowlist ontbreekt (met een knop om het toe te voegen) en haalt de noscript-regel van EWWW Lazy Load uit de kritieke CSS, zodat lazy-load-afbeeldingen niet onzichtbaar blijven. Doet niets zonder LiteSpeed Cache.',
			'bestand'      => 'quic-cloud.php',
			'standaard'    => false,
			'losse_mu'     => '',
			'losse_plugin' => '',
			'merkteken'    => array(),
			'scherm'       => '',
			'vereist'      => '',
		),
		'schema_basis'      => array(
			'naam'         => 'Schema op alle pagina\'s',
			'uitleg'       => 'Zorgt dat Rank Math de gegevens van het bedrijf (Organization, WebSite en WebPage) ook op gewone pagina\'s en berichten zet, niet alleen op de homepage. Nodig als het standaardschema voor pagina\'s in Rank Math op "Geen" staat. Voegt geen Article-schema toe en verandert niets op categorie- en tagpagina\'s. Doet niets zonder Rank Math.',
			'bestand'      => 'schema-basis.php',
			'standaard'    => true,
			'losse_mu'     => '',
			'losse_plugin' => '',
			'merkteken'    => array(),
			'scherm'       => '',
			'vereist'      => '',
		),
	);
}
