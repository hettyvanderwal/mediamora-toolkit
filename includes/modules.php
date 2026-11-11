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
 * losse_mu:        mu-plugin die deze module vervangt
 * losse_plugin:    gewone plugin die deze module vervangt
 * merkteken:       functie of klasse die de losse versie definieert. Laadt
 *                  die al, dan blijft de module uit, anders botsen de
 *                  functienamen en ligt de site plat.
 * vereist:         plugin die aan moet staan, anders is de module niet aan
 *                  te zetten. Nu alleen 'woocommerce'.
 * thema_merkteken: functie uit testcode in functions.php van het thema.
 *                  Die laadt na de plugins, dus bij het laden van de
 *                  modules is hij nog niet te zien en laadt de module
 *                  gewoon. De module kijkt er zelf naar in zijn hooks en
 *                  doet dan niets. Na after_setup_theme telt
 *                  mm_toolkit_status() de testcode als losse versie, zodat
 *                  het instellingenscherm en de melding hem tonen.
 * tabblad:         sleutel uit mm_toolkit_tabbladen(), het tabblad waarop
 *                  de module in het instellingenscherm staat. Alleen de
 *                  indeling; aan de opgeslagen keuzes verandert niets.
 * onderhoud:       hoort bij het onderhoudspakket. Staat de schakelaar
 *                  Onderhoudsklant uit, dan laadt de module niet, ongeacht
 *                  zijn eigen schakelaar of constante.
 * ---------------------------------------------------------------------- */

/**
 * De tabbladen van het instellingenscherm, in deze volgorde. Een intern
 * tabblad is alleen te zien en op te slaan voor Mediamora zelf, zie
 * mm_toolkit_mediamora_gebruiker().
 */
function mm_toolkit_tabbladen() {
	return array(
		'snelheid'     => array( 'naam' => 'Snelheid', 'intern' => false ),
		'beveiliging'  => array( 'naam' => 'Beveiliging', 'intern' => false ),
		'vindbaarheid' => array( 'naam' => 'Vindbaarheid', 'intern' => false ),
		'webshop'      => array( 'naam' => 'Webshop', 'intern' => false ),
		'koppelingen'  => array( 'naam' => 'Koppelingen', 'intern' => false ),
		'bouw'         => array( 'naam' => 'Bouw', 'intern' => false ),
		'mediamora'    => array( 'naam' => 'Mediamora', 'intern' => true ),
	);
}

/**
 * Is dit een e-mailadres van Mediamora? Kijkt naar het domein, standaard
 * mediamora.nl, aan te passen met het filter mm_toolkit_mediamora_domein.
 * Geen subdomeinen; hoofdletters maken niet uit.
 *
 * @param string $adres
 * @return bool
 */
function mm_toolkit_mediamora_adres( $adres ) {
	$domein = strtolower( ltrim( trim( (string) apply_filters( 'mm_toolkit_mediamora_domein', 'mediamora.nl' ) ), '@' ) );
	$adres  = strtolower( (string) $adres );
	$at     = strrpos( $adres, '@' );
	return '' !== $domein && false !== $at && substr( $adres, $at + 1 ) === $domein;
}

function mm_toolkit_modules() {
	return array(
		'alt_teksten'       => array(
			'naam'            => 'Alt-teksten',
			'uitleg'          => 'Vult bij nieuwe uploads zelf een alt-tekst in, zodat Elementor geen bestandsnamen toont. Niet nodig op een academie.',
			'bestand'         => 'alt-teksten.php',
			'standaard'       => true,
			'losse_mu'        => 'mediamora-alt-teksten.php',
			'losse_plugin'    => '',
			'merkteken'       => array( 'class', 'Mediamora_Alt_Teksten' ),
			'scherm'          => '',
			'vereist'         => '',
			'thema_merkteken' => '',
			'tabblad'         => 'vindbaarheid',
			'onderhoud'       => false,
		),
		'hero_preload'      => array(
			'naam'            => 'Hero-preload',
			'uitleg'          => 'Laat de achtergrondafbeelding of slideshow van de bovenste container vooraf laden. Scheelt vooral op mobiel op de LCP. Werkt op pagina\'s en posts, en op winkel-, categorie-, blog- en zoekpagina\'s via het archieftemplate van Elementor Pro.',
			'bestand'         => 'hero-preload.php',
			'standaard'       => true,
			'losse_mu'        => 'mediamora-hero-preload.php',
			'losse_plugin'    => '',
			'merkteken'       => array(),
			'scherm'          => '',
			'vereist'         => '',
			'thema_merkteken' => '',
			'tabblad'         => 'snelheid',
			'onderhoud'       => false,
		),
		'preview_link'      => array(
			'naam'            => 'Preview-link',
			'uitleg'          => 'Klanten kijken mee op een site in onderhoudsmodus via een link, zonder inloggen. Doet niets zodra de onderhoudsmodus uit staat. Uitzetten maakt alle bestaande links ongeldig.',
			'bestand'         => 'preview-link.php',
			'standaard'       => true,
			'losse_mu'        => 'mm-preview.php',
			'losse_plugin'    => '',
			'merkteken'       => array( 'function', 'mm_preview_key' ),
			'scherm'          => '',
			'vereist'         => '',
			'thema_merkteken' => '',
			'tabblad'         => 'bouw',
			'onderhoud'       => false,
		),
		'anti_spam'         => array(
			'naam'            => 'Anti-spam',
			'uitleg'          => 'Weigert spam via Elementor Pro-formulieren. Werkt alleen als Elementor Pro actief is.',
			'bestand'         => 'anti-spam.php',
			'standaard'       => true,
			'losse_mu'        => '',
			'losse_plugin'    => 'mediamora-anti-spam-elementor/mediamora-anti-spam-elementor.php',
			'merkteken'       => array( 'function', 'mediamora_antispam_validate_form' ),
			'scherm'          => 'options-general.php?page=mediamora-antispam',
			'vereist'         => '',
			'thema_merkteken' => '',
			'tabblad'         => 'beveiliging',
			'onderhoud'       => false,
		),
		'formuliermonitor'  => array(
			'naam'            => 'Formuliermonitor',
			'uitleg'          => 'Stuurt Mediamora een melding zodra de site een e-mail niet kan versturen. Hoort bij het onderhoudspakket.',
			'bestand'         => 'formuliermonitor.php',
			'standaard'       => false,
			'losse_mu'        => '',
			'losse_plugin'    => 'mediamora-formuliermonitor/mediamora-formuliermonitor.php',
			'merkteken'       => array( 'function', 'mm_monitor_verwerk_fout' ),
			'scherm'          => '',
			'vereist'         => '',
			'thema_merkteken' => '',
			'tabblad'         => 'mediamora',
			'onderhoud'       => true,
		),
		'ai_bots'           => array(
			'naam'            => 'AI-bots',
			'uitleg'          => 'Houdt per maand bij welke AI-crawlers de site bezoeken. Hoort bij de GEO-check.',
			'bestand'         => 'ai-bots.php',
			'standaard'       => false,
			'losse_mu'        => 'mediamora-ai-bots.php',
			'losse_plugin'    => '',
			'merkteken'       => array( 'function', 'mm_aibots_lijst' ),
			'scherm'          => 'options-general.php?page=mm-aibots',
			'vereist'         => '',
			'thema_merkteken' => '',
			'tabblad'         => 'vindbaarheid',
			'onderhoud'       => false,
		),
		'rest_users'        => array(
			'naam'            => 'REST-gebruikers afschermen',
			'uitleg'          => 'Sluit /wp-json/wp/v2/users af voor bezoekers die niet zijn ingelogd, zodat inlognamen niet uit te lezen zijn. De rest van de REST API blijft werken. Alleen nodig op een webshop, want daar blokkeert "Disable REST API" in ASE de webhooks van Mollie en MyParcel. Op andere sites blijft ASE de route.',
			'bestand'         => 'rest-users.php',
			'standaard'       => false,
			'losse_mu'        => 'mediamora-rest-users.php',
			'losse_plugin'    => '',
			'merkteken'       => array( 'function', 'mm_rest_users_afschermen' ),
			'scherm'          => '',
			'vereist'         => '',
			'thema_merkteken' => '',
			'tabblad'         => 'beveiliging',
			'onderhoud'       => false,
		),
		// Geen merkteken voor de oude functions.php-code (mm_withdrawal_text,
		// mm_consent_text): die laadt pas na de plugins. De module kijkt daar
		// zelf per soort naar op init.
		'withdrawal_waiver' => array(
			'naam'            => 'Herroepingsrecht bij afrekenen',
			'uitleg'          => 'Verplichte vinkjes boven de bestelknop: bij digitale content (zoals een online cursus) stemt de koper in met directe toegang en ziet af van het herroepingsrecht, bij een dienst (zoals coaching of een consult) stemt hij in met directe uitvoering. Per product, categorie of standaard in te stellen. De tekst komt op de bestelling, in de bestelmail en in het beheer. Alleen voor webshops: zonder WooCommerce is hij niet aan te zetten. Werkt met de klassieke checkout en de Checkout-widget van Elementor, niet met het checkoutblok.',
			'bestand'         => 'withdrawal-waiver.php',
			'standaard'       => false,
			'losse_mu'        => '',
			'losse_plugin'    => '',
			'merkteken'       => array(),
			'scherm'          => 'options-general.php?page=mm-herroeping',
			'vereist'         => 'woocommerce',
			'thema_merkteken' => '',
			'tabblad'         => 'webshop',
			'onderhoud'       => false,
		),
		// De drie modules hieronder draaiden eerst als test in functions.php,
		// als closures en dus zonder merkteken. Ze herkennen die testcode zelf.
		'popup_tik'         => array(
			'naam'            => 'Mobiel menu: vroege tik',
			'uitleg'          => 'Een tik op de hamburger voordat Elementor Pro klaar is ging verloren. Deze module onthoudt de tik en opent de popup zodra Elementor Pro zover is. Is Elementor Pro na 15 seconden nog niet klaar, dan stopt hij en gaat elke tik weer gewoon door. Doet niets zonder Elementor Pro.',
			'bestand'         => 'popup-tik.php',
			'standaard'       => true,
			'losse_mu'        => '',
			'losse_plugin'    => '',
			'merkteken'       => array(),
			'scherm'          => '',
			'vereist'         => '',
			'thema_merkteken' => '',
			'tabblad'         => 'snelheid',
			'onderhoud'       => false,
		),
		'jquery_wacht'      => array(
			'naam'            => 'jQuery-wachtrem',
			'uitleg'          => 'Met jQuery uitgesteld (LiteSpeed defer) kon Elementor starten voordat Elementor Pro luisterde, waarna mobiel menu, sticky headers en Pro-formulieren die paginaweergave niet werkten. Deze module houdt jQuery.ready vast tot DOMContentLoaded, met als vangnet het load-event, zodat jQuery.ready nooit blijft hangen als het regeltje pas na DOMContentLoaded uitvoert. Doet niets als jQuery niet is uitgesteld. Gebruikt jQuery.holdReady, dat verouderd is in jQuery 3: bij een overstap naar jQuery 4 opnieuw bekijken.',
			'bestand'         => 'jquery-wacht.php',
			'standaard'       => true,
			'losse_mu'        => '',
			'losse_plugin'    => '',
			'merkteken'       => array(),
			'scherm'          => '',
			'vereist'         => '',
			'thema_merkteken' => '',
			'tabblad'         => 'snelheid',
			'onderhoud'       => false,
		),
		'quic_cloud'        => array(
			'naam'            => 'QUIC.cloud en kritieke CSS',
			'uitleg'          => 'Aanzetten als de kritieke CSS van LiteSpeed via QUIC.cloud wordt ingericht. Laat QUIC.cloud terugbellen ondanks "REST API uitschakelen" in ASE, meldt het als #elementor-device-mode in de CCSS-allowlist ontbreekt (met een knop om het toe te voegen) en haalt de noscript-regel van EWWW Lazy Load uit de kritieke CSS, zodat lazy-load-afbeeldingen niet onzichtbaar blijven. Doet niets zonder LiteSpeed Cache.',
			'bestand'         => 'quic-cloud.php',
			'standaard'       => false,
			'losse_mu'        => '',
			'losse_plugin'    => '',
			'merkteken'       => array(),
			'scherm'          => '',
			'vereist'         => '',
			'thema_merkteken' => '',
			'tabblad'         => 'snelheid',
			'onderhoud'       => false,
		),
		'schema_basis'      => array(
			'naam'            => 'Schema op alle pagina\'s',
			'uitleg'          => 'Zorgt dat Rank Math de gegevens van het bedrijf (Organization, WebSite en WebPage) ook op gewone pagina\'s en berichten zet, niet alleen op de homepage. Nodig als het standaardschema voor pagina\'s in Rank Math op "Geen" staat. Voegt geen Article-schema toe en verandert niets op categorie- en tagpagina\'s. Doet niets zonder Rank Math.',
			'bestand'         => 'schema-basis.php',
			'standaard'       => true,
			'losse_mu'        => '',
			'losse_plugin'    => '',
			'merkteken'       => array(),
			'scherm'          => '',
			'vereist'         => '',
			'thema_merkteken' => '',
			'tabblad'         => 'vindbaarheid',
			'onderhoud'       => false,
		),
		'afbeelding_ruimte' => array(
			'naam'            => 'Afbeeldingen: ruimte en voorrang',
			'uitleg'          => 'Geeft afbeeldingen van Elementor met een eigen uitsnede (uploads/elementor/thumbs) de echte width en height, zodat de pagina niet verspringt als EWWW de bovenste afbeeldingen niet lazyloadt. Afbeeldingswidgets in de bovenste sectie van de pagina of het Theme Builder-template laden direct (loading="eager" en skip-lazy), want daar staat vaak de LCP. Header, footer, popups en loop-items blijven zoals ze zijn. Zet geen fetchpriority, dat doet Hero-preload. Na aanzetten de cache van Elementor en LiteSpeed legen.',
			'bestand'         => 'afbeelding-ruimte.php',
			'standaard'       => true,
			'losse_mu'        => '',
			'losse_plugin'    => '',
			'merkteken'       => array(),
			'scherm'          => '',
			'vereist'         => '',
			'thema_merkteken' => 'mm_ig_elementor_img_attrs',
			'tabblad'         => 'snelheid',
			'onderhoud'       => false,
		),
		'elementor_versie'  => array(
			'naam'            => 'Elementor: oude versies tegenhouden',
			'uitleg'          => 'Voorkomt dat een oud tabblad van de Elementor-editor nieuwer werk overschrijft. Elk document krijgt bij het openen een vingerafdruk van de opgeslagen inhoud. Is het document daarna veranderd, door iemand anders, in een ander tabblad of via Novamira, REST of WP-CLI, dan weigert Elementor het opslaan met de melding om eerst te herladen. Geldt voor pagina\'s, berichten, templates, popups, header, footer en de site-instellingen. Autosaves naar een revisie en het voorbeeld gaan gewoon door; bij een concept schrijft de autosave het document zelf en telt hij als opslaan. Wijzigingen buiten de editor worden niet tegengehouden. Doet niets zonder Elementor en niets op de voorkant.',
			'bestand'         => 'elementor-versie.php',
			'standaard'       => true,
			'losse_mu'        => '',
			'losse_plugin'    => '',
			'merkteken'       => array(),
			'scherm'          => '',
			'vereist'         => '',
			'thema_merkteken' => '',
			'tabblad'         => 'beveiliging',
			'onderhoud'       => false,
		),
		'meta_opruimen'     => array(
			'naam'            => 'Verweesde meta opruimen',
			'uitleg'          => 'Haalt elke donderdag rond 05:00 regels uit wp_postmeta en wp_commentmeta weg waarvan de post of reactie niet meer bestaat. Die blijven achter na de wekelijkse database-opschoning van MainWP, die revisies en reacties zonder hun meta verwijdert. Werkt in kleine batches met een tijdsbudget en gaat later verder als dat op is. Geen OPTIMIZE TABLE. Verweesde regels in wp_term_relationships worden alleen geteld. Na het aanzetten wordt niets meteen opgeruimd; met Nu tellen is te zien wat er de eerste donderdag weg gaat.',
			'bestand'         => 'meta-opruimen.php',
			'standaard'       => true,
			'losse_mu'        => '',
			'losse_plugin'    => '',
			'merkteken'       => array(),
			'scherm'          => '',
			'vereist'         => '',
			'thema_merkteken' => '',
			'tabblad'         => 'mediamora',
			'onderhoud'       => true,
		),
		'instagram'         => array(
			'naam'            => 'Instagram-feed',
			'uitleg'          => 'Toont de laatste Instagram-posts met de shortcode [mm_instagram], bijvoorbeeld in de footer. De site haalt de JSON-feed van Behold twee keer per dag op en zet de foto\'s op de eigen server, dus bezoekers laden niets van Behold of Instagram en bij Behold tellen alleen die ophaalacties als views (zo\'n 60 per maand). Feed-URL en aantal posts onder Instellingen > Instagram-feed.',
			'bestand'         => 'instagram.php',
			'standaard'       => false,
			'losse_mu'        => '',
			'losse_plugin'    => '',
			'merkteken'       => array(),
			'scherm'          => 'options-general.php?page=mm-instagram',
			'vereist'         => '',
			'thema_merkteken' => '',
			'tabblad'         => 'koppelingen',
			'onderhoud'       => false,
		),
		// Op het tabblad Mediamora, maar bewust niet onder Onderhoudsklant: ook
		// voor klanten zonder onderhoud. De losse versie bestaat uit closures,
		// dus zonder merkteken; het bestand zelf is het teken.
		'tijdelijke_accounts' => array(
			'naam'            => 'Tijdelijke accounts',
			'uitleg'          => 'Accounts van externe partijen verlopen automatisch, zodat er geen beheerdersaccounts blijven rondslingeren. Bij een gebruiker zijn een verloopdatum en een erfgenaam voor de content in te stellen. Vanaf de verloopdatum wordt het account overal geweigerd; de dagelijkse cron verwijdert het en draagt de content over. Zonder geldige erfgenaam blijft het account geblokkeerd staan. Niet voor accounts van Mediamora.',
			'bestand'         => 'tijdelijke-accounts.php',
			'standaard'       => true,
			'losse_mu'        => 'mediamora-tijdelijke-accounts.php',
			'losse_plugin'    => '',
			'merkteken'       => array(),
			'scherm'          => '',
			'vereist'         => '',
			'thema_merkteken' => '',
			'tabblad'         => 'mediamora',
			'onderhoud'       => false,
		),
		// Geen merkteken voor de tijdelijke code op drentz.nl: die staat in
		// functions.php, laadt na de plugins en slaat zichzelf over zodra deze
		// module mm_verberg_afbeeldingstitel heeft gedefinieerd.
		'afbeeldingstitels' => array(
			'naam'            => 'Afbeeldingstitels verbergen',
			'uitleg'          => 'Met een eigen uitsnede (bijvoorbeeld 1000x1333) zet Elementor de titel uit de mediabibliotheek als title op de afbeelding, en bezoekers zien bij hover dan de bestandsnaam. Deze module haalt alleen dat title-attribuut weg; de alt-tekst en de rest blijven staan. Geldt voor de afbeeldingswidget en de andere widgets die de afbeelding op dezelfde manier opbouwen, zoals Afbeeldingsvak, Call to action en Flip box. Bij aan- of uitzetten worden de element cache van Elementor en de LiteSpeed-cache geleegd. Doet niets zonder Elementor.',
			'bestand'         => 'afbeeldingstitels.php',
			'standaard'       => true,
			'losse_mu'        => '',
			'losse_plugin'    => '',
			'merkteken'       => array(),
			'scherm'          => '',
			'vereist'         => '',
			'thema_merkteken' => '',
			'tabblad'         => 'vindbaarheid',
			'onderhoud'       => false,
		),
	);
}

/**
 * Staat de losse versie van deze module als bestand op de site, als plugin
 * of mu-plugin, actief of niet? Gebruikt bij het opruimen van gedeelde data,
 * zodat een losse versie die (weer) aangaat zijn opties nog heeft.
 *
 * @param array $module Een regel uit mm_toolkit_modules().
 * @return bool
 */
function mm_toolkit_los_bestand_aanwezig( $module ) {

	if ( $module['losse_mu'] && defined( 'WPMU_PLUGIN_DIR' ) && file_exists( WPMU_PLUGIN_DIR . '/' . $module['losse_mu'] ) ) {
		return true;
	}

	if ( $module['losse_plugin'] && defined( 'WP_PLUGIN_DIR' ) && file_exists( WP_PLUGIN_DIR . '/' . $module['losse_plugin'] ) ) {
		return true;
	}

	return false;
}
