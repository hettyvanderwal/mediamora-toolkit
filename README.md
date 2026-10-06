# Mediamora Toolkit

De vaste Mediamora-onderdelen in één WordPress-plugin. Elk onderdeel is een module die per site aan of uit staat onder **Instellingen > Mediamora Toolkit**.

| Module | Standaard | Vervangt |
|---|---|---|
| Alt-teksten | aan | mu-plugin `mediamora-alt-teksten.php` |
| Hero-preload | aan | mu-plugin `mediamora-hero-preload.php` |
| Preview-link | aan | mu-plugin `mm-preview.php` |
| Anti-spam | aan | plugin `mediamora-anti-spam-elementor` |
| Formuliermonitor | uit | plugin `mediamora-formuliermonitor` |
| AI-bots | uit | mu-plugin `mediamora-ai-bots.php` |
| REST-gebruikers afschermen | uit | mu-plugin `mediamora-rest-users.php` |
| Herroepingsrecht bij afrekenen | uit, alleen met WooCommerce | code in `functions.php` (`mm_withdrawal_text`, `mm_consent_text`) |
| Mobiel menu: vroege tik | aan | testcode in `functions.php` (script `mm-popup-vroege-tik`) |
| jQuery-wachtrem | aan | testcode in `functions.php` (`jQuery.holdReady` achter `jquery-core`) |
| QUIC.cloud en kritieke CSS | uit | testcode in `functions.php` (REST-uitzondering, `mm_ccss_zonder_noscript`) |
| Schema op alle pagina's | aan, doet niets zonder Rank Math | nieuw |

## Overstappen

Staat de losse versie van een module nog op de site, dan laadt de toolkit die module niet en toont hij een melding. De losse versie blijft dan gewoon werken. Pas als de losse versie weg is, neemt de module het over. Instellingen, logs en optienamen zijn gelijk gebleven, dus er gaat niets verloren.

Bij de eerste activering komt elke module aan waarvan een losse versie op de site staat, ook als die module standaard uit staat.

De opgeslagen keuzes gaan altijd voor op de standaard. Wordt de standaard van een module later omgezet, dan houdt een site die daar zelf een keuze voor heeft opgeslagen gewoon wat hij had.

Herroepingsrecht bij afrekenen vervangt code in `functions.php`. Die laadt na de plugins, dus de module kijkt pas op `init` welke oude code er staat, per soort: `mm_withdrawal_text` voor digitale content, `mm_consent_text` voor diensten. Staat de oude code van een soort er, dan doet de module voor die soort niets (geen vinkje, validatie, opslag, mail, herinnering of beheer) en staat er een melding in de instellingen. De andere soort werkt gewoon. De module mag al aan staan; hij neemt een soort over zodra de oude code daarvan weg is. De metakeys `_mm_withdrawal_waiver` (digitaal) en `_mm_service_consent` (dienst) op bestellingen zijn gelijk aan die van de oude code.

## Herroepingsrecht: welke soort per product

De module kent twee soorten, elk met een eigen vinkje, eigen teksten en een eigen metakey op de bestelling:

- **Digitale content** (`_mm_withdrawal_waiver`), zoals een online cursus. De koper stemt in met directe toegang en verklaart zijn herroepingsrecht te verliezen. Het recht vervalt bij directe levering.
- **Dienst** (`_mm_service_consent`), zoals healing, coaching of een consult. De koper stemt in met directe uitvoering. Het recht vervalt pas als de dienst volledig is uitgevoerd; tot dan blijft het herroepingsrecht, en daarmee de herroepingsknop, gelden. Zet daarom Order withdrawal van WooCommerce aan (Geavanceerd > Features); de instellingen waarschuwen als dat nog uit staat.

Welke soort een product heeft, in deze volgorde:

1. De keuze Herroepingsrecht in de tab Algemeen van het product (`_mm_withdrawal_type`): Standaard, Digitale content, Dienst of Geen vinkje.
2. Bij Standaard: de categorieën van het product. Per soort zijn categorieën te kiezen onder Instellingen > Herroepingsrecht; subcategorieën tellen mee. Staat een product in categorieën van beide soorten, dan geldt de soort van de eerste categorie op alfabetische volgorde van de naam (de volgorde van `get_the_terms`; de module sorteert zelf op naam, omdat WooCommerce productcategorieën ook op de eigen volgorde uit het beheer kan zetten). Hoort één categorie, direct of via een bovenliggende, bij beide soorten, dan wint digitaal. Er wordt niets gelogd.
3. Anders de standaardsoort: Digitale content (standaard), Dienst of Geen vinkje.

Bij een variatie telt het hoofdproduct. Per soort die in de winkelmand zit komt één verplicht vinkje, onder het voorwaardenvinkje en boven de bestelknop; zitten beide soorten erin, dan eerst digitaal en dan dienst. Tonen, valideren, opslaan, mail en beheer gebruiken allemaal dezelfde soortbepaling.

Per soort zijn de tekst bij het vinkje, de foutmelding, het label in mail en beheer en een herinnering in te stellen. Leeg geeft de standaardtekst in het Nederlands (site-taal `nl_*`) of Engels. De herinnering heeft geen standaardtekst; ingevuld staat hij in kleine grijze letters onderaan de mail "Bestelling in behandeling" (`customer_processing_order`), alleen bij bestellingen met de metakey van die soort. Springt een bestelling direct op Afgerond, dan gaat die mail niet en komt de herinnering dus niet aan.

Per soort staat er een vinkje "Regel in de bestelmail" (`digitaal_mailregel`, `dienst_mailregel`), standaard aan. Uit laat de regel met label en opgeslagen tekst weg uit de bestelmails, naar koper en beheerder; op de bestelling en onder het factuuradres in het beheer blijft hij staan. Staat de regel van een soort uit en is de herinnering van die soort leeg, dan geven de instellingen een melding: de mail bevestigt de instemming dan nergens meer, en bij digitale content is die bevestiging op een duurzame drager verplicht.

Mobiel menu: vroege tik, jQuery-wachtrem en QUIC.cloud en kritieke CSS vervangen testcode in `functions.php`. Die testcode bestaat uit closures zonder vaste functienaam, dus de modules hebben geen merkteken en laden altijd. Ze herkennen de testcode zelf en doen dan niets dubbel: de vroege tik ziet het script `mm-popup-vroege-tik` in de pagina, de jQuery-wachtrem ziet `holdReady` al in de inline scripts van `jquery-core`, en de CCSS-opschoning ziet de functie `mm_ccss_zonder_noscript`. Een dubbele REST-uitzondering kan geen kwaad. De testblokken kunnen dus blijven staan tot na de update, en daarna weg.

REST-gebruikers afschermen ging van standaard aan naar standaard uit. Sites die daar nog geen keuze voor hadden opgeslagen, krijgen die eenmalig alsnog, maar alleen als WooCommerce aanstaat: een webshop houdt de module zo aan. Op een site zonder WooCommerce komt de module uit te staan en is ASE met "Disable REST API" weer de route.

## Vastzetten per site

In `wp-config.php`, bijvoorbeeld op een academie:

```php
define( 'MM_TOOLKIT_MODULE_ALT_TEKSTEN', false );
```

Beschikbaar: `MM_TOOLKIT_MODULE_ALT_TEKSTEN`, `MM_TOOLKIT_MODULE_HERO_PRELOAD`, `MM_TOOLKIT_MODULE_PREVIEW_LINK`, `MM_TOOLKIT_MODULE_ANTI_SPAM`, `MM_TOOLKIT_MODULE_FORMULIERMONITOR`, `MM_TOOLKIT_MODULE_AI_BOTS`, `MM_TOOLKIT_MODULE_REST_USERS`, `MM_TOOLKIT_MODULE_WITHDRAWAL_WAIVER`, `MM_TOOLKIT_MODULE_POPUP_TIK`, `MM_TOOLKIT_MODULE_JQUERY_WACHT`, `MM_TOOLKIT_MODULE_QUIC_CLOUD`, `MM_TOOLKIT_MODULE_SCHEMA_BASIS`.

De oude namen zonder `MODULE_` (zoals `MM_TOOLKIT_ALT_TEKSTEN`) werken nog voor de eerste zeven modules, zodat bestaande regels in `wp-config.php` niet aangepast hoeven te worden. Staan beide er, dan wint de nieuwe naam. Een vastgezette module houdt bij het opslaan van het instellingenscherm de keuze die er al stond.

## Deactiveren en verwijderen

Deactiveren haalt alleen de cron-events en de blokken van de toolkit uit `.htaccess` weg. Instellingen en logs blijven staan.

Verwijderen via Plugins ruimt alles op: de opties en transients van de toolkit en de modules, de cron-events, de alt-tekstkenmerken (`_mm_alt_*`, de alt-teksten zelf blijven staan), de productmeta van Herroepingsrecht (`_mm_withdrawal_type` en het oude vinkje `_mm_withdrawal_waiver_applies`; de teksten `_mm_withdrawal_waiver` en `_mm_service_consent` op bestellingen blijven altijd staan, dat is het bewijs van de instemming), de `.htaccess`-blokken en de logmap van de anti-spam met de inzendingen erin. Kan de logmap niet worden weggehaald, dan blijft de optie `mediamora_antispam_log_dir` staan, zodat te vinden is waar hij staat. Staat de losse versie van een module nog op de site, als plugin of mu-plugin en actief of niet, dan blijft alles van die module staan, want de losse versie gebruikt dezelfde gegevens.

## Wijzigingen

### 1.11.0

- Herroepingsrecht: per soort een nieuwe instelling "Regel in de bestelmail" in het tekstenblok (`digitaal_mailregel`, `dienst_mailregel`: `ja` of `nee`). Ontbreekt de sleutel, zoals op sites die na de update nog niet hebben opgeslagen, dan staat hij aan en verandert er niets. Uitgevinkt wordt `nee` opgeslagen.
- Herroepingsrecht: `mm_herroeping_mail()` slaat een soort over als de mailregel uit staat, in de mails naar de koper en naar de beheerder. De tekst op de bestelling en de regel onder het factuuradres in het beheer blijven ongewijzigd, net als de herinnering.
- Herroepingsrecht: melding in de instellingen als de mailregel van een soort uit staat en de herinnering van die soort leeg is. De mail bevestigt de instemming dan nergens meer; bij digitale content is die bevestiging op een duurzame drager verplicht. Soorten waarvan de oude `functions.php`-code nog actief is krijgen deze melding niet.
- Geen nieuwe optie: de instelling staat in `mm_withdrawal_waiver_settings`, die `uninstall.php` al opruimt.

### 1.10.0

- Nieuwe module Schema op alle pagina's (`schema_basis`), standaard aan. Staat in Rank Math het standaardschema voor pagina's op "Geen" (`pt_page_default_rich_snippet` = `off`), dan laat Rank Math op pagina's zonder eigen schema ook Organization, WebSite en WebPage weg; alleen de homepage krijgt ze dan nog (`can_add_global_entities()` in `class-jsonld.php`). Bij een controle van buitenaf hadden 11 van de 42 meetbare Rank Math-sites daardoor gewone pagina's zonder Organization en WebPage. De module geeft via het filter `rank_math/schema/add_global_entities` `true` terug op pagina's en berichten. Op categorie-, tag- en taxonomiepagina's blijft de waarde van Rank Math ongewijzigd, zodat `remove_<taxonomy>_snippet_data` blijft gelden. De module verandert niets aan `pt_page_default_rich_snippet` en voegt geen schematype toe: er komt geen Article op pagina's. Op sites met Article als paginastandaard roept Rank Math het filter niet aan en verandert er dus niets. Het filter wordt pas op `plugins_loaded` gezet en alleen als `RANK_MATH_VERSION` bestaat, want de toolkit laadt vóór Rank Math. De module slaat niets op, dus `uninstall.php` hoeft er niets voor op te ruimen.

### 1.9.1

- Hero-preload: de preload kiest nu het eerste element met een echte achtergrondafbeelding binnen de hero-sectie, in plaats van het eerste element met een achtergrond. De hero-sectie is, net als voorheen, het bovenste top-level element waarin of waaronder een `background_background` staat. Daarbinnen tellen alleen classic met een afbeelding (vast of via dynamic tag) en slideshow met een galerij (vast of via dynamic tag); een kaart met alleen een kleur, een verloop of een video wordt overgeslagen. Op Falcon-i stond een kleurkaart boven de fotocontainer, waardoor er geen preload kwam. Staat er in de hero-sectie geen afbeelding (een effen hero), dan geen preload: er wordt niet doorgezocht naar een volgende sectie.
- Hero-preload: de controle op `background_image_tablet` en `background_image_mobile` kijkt alleen nog naar het gekozen element, met een niet-lege url of een dynamic tag. Voorheen zette zo'n sleutel ergens op de pagina, ook met een lege url, de preload voor de hele pagina uit.
- Hero-preload: bij een slideshow komt de CSS-achtergrond van de eerste dia op het gekozen element, ook als dat genest is. Voorheen kwam die altijd op de bovenste container.

### 1.9.0

- Nieuwe module Mobiel menu: vroege tik (`popup_tik`), standaard aan. De hamburger opent een popup van Elementor Pro, maar is al zichtbaar voordat Elementor Pro klaar is; een tik in die tussentijd ging verloren. De module vangt zo'n tik op en opent de bedoelde popup zodra Elementor Pro zover is, met een herhaallus omdat de eerste `showPopup` soms wordt genegeerd. Valt de tik na het load-event, dan start de lus meteen. Is Elementor Pro 15 seconden na de tik nog niet klaar, dan stopt het onderscheppen voor die paginaweergave, zodat er nooit een dode knop ontstaat. Het script staat in de head met `data-no-defer` en `data-no-optimize`, zodat LiteSpeed het niet uitstelt. Alleen op de voorkant, alleen met Elementor Pro, niet in de editor of het voorbeeld van Elementor. De vlag `window.mmPopupVroegeTik` voorkomt dat het twee keer actief wordt; staat de testversie nog in `functions.php`, dan doet de module niets.
- Nieuwe module jQuery-wachtrem (`jquery_wacht`), standaard aan. Met jQuery uitgesteld (LiteSpeed defer) kon Elementor starten voordat Elementor Pro luisterde, waarna Pro die paginaweergave niet startte: geen mobiel menu, sticky headers of Pro-formulieren. De module zet direct achter `jquery-core` een regel die `jQuery.ready` vasthoudt tot `DOMContentLoaded`, met het load-event als vangnet (voert de regel pas na `DOMContentLoaded` uit, bijvoorbeeld met LiteSpeed "JS vertraagd", dan laat `load` alsnog los; een eigen vlag zorgt dat `holdReady(false)` maar één keer wordt aangeroepen), zonder `data-no-defer`, zodat hij net als jQuery uitgesteld loopt. Doet niets als jQuery niet is uitgesteld of `holdReady` ontbreekt. `holdReady` is verouderd in jQuery 3; bij een overstap naar jQuery 4 opnieuw bekijken. Staat de testversie nog in `functions.php`, dan wordt de regel niet nog een keer toegevoegd.
- Nieuwe module QUIC.cloud en kritieke CSS (`quic_cloud`), standaard uit. Per site aanzetten als de kritieke CSS van LiteSpeed via QUIC.cloud wordt ingericht. Drie onderdelen:
  - REST-uitzondering: heeft iets (zoals "REST API uitschakelen" in ASE) een REST-verzoek op een route die begint met `/litespeed/` al geweigerd, dan laat de module het toch door, zodat QUIC.cloud kan terugbellen. Gefilterd op `rest_route`, ook bij `?rest_route=`. Werkt naast REST-gebruikers afschermen.
  - Allowlist-bewaking: staat CSS asynchroon laden (`optm-css_async`) aan en ontbreekt `#elementor-device-mode` in de CCSS-allowlist (`optm-ccss_whitelist`), dan krijgen beheerders een melding met een knop Toevoegen. Die voegt het toe aan de bestaande lijst via `\LiteSpeed\Conf::cls()->update_confs()`, met nonce en capability-check. Zonder klik verandert er niets.
  - De regel `.lazyload[data-src]{display:none !important;}` uit de noscript van EWWW Lazy Load wordt uit de kritieke CSS gehaald, zodat lazy-load-afbeeldingen niet onzichtbaar blijven.
- Anti-spam: drie em dashes in commentaar vervangen door een gewoon streepje.
- Geen van de drie modules slaat iets op, dus `uninstall.php` hoeft er niets voor op te ruimen.

### 1.8.0

- Herroepingsrecht: naast digitale content nu ook diensten (healing, coaching, consult). Twee soorten, elk met een eigen verplicht vinkje, eigen teksten en een eigen metakey op de bestelling: `_mm_withdrawal_waiver` (digitaal, ongewijzigd) en `_mm_service_consent` (dienst, gelijk aan de oude `functions.php`-code). Zitten beide soorten in de winkelmand, dan komen er twee vinkjes, eerst digitaal en dan dienst, elk apart verplicht.
- Herroepingsrecht: het productvinkje is vervangen door een keuzeveld in de tab Algemeen (`_mm_withdrawal_type`: Standaard, Digitale content, Dienst, Geen vinkje). In de instellingen een standaardsoort (standaard Digitale content) en per soort een categoriekeuze. Volgorde: product, dan categorie, dan standaardsoort. Zie "Herroepingsrecht: welke soort per product".
- Herroepingsrecht: per soort een herinnering, die in kleine grijze letters onderaan de mail "Bestelling in behandeling" komt bij bestellingen met die soort. Standaard leeg, dus uit. Ook in de platte-tekstmail.
- Herroepingsrecht: de module geeft een niet aangevinkt vakje zelf de rode rand, net als WooCommerce bij het voorwaardenvinkje. Een kleine inline style, alleen op de afrekenpagina.
- Herroepingsrecht: oude code per soort. `mm_withdrawal_text` in `functions.php` zet alleen digitaal stil, `mm_consent_text` alleen dienst; de andere soort werkt gewoon.
- Herroepingsrecht: melding in de instellingen als er iets op Dienst staat (standaardsoort, categorie of product) en Order withdrawal van WooCommerce uit staat.
- Herroepingsrecht: de instellingen van 1.6.0 worden eenmalig omgezet op `init`, direct na de update en ook als de module uit staat of niemand de instellingenpagina opent. Bereik "Alle producten" wordt standaardsoort Digitale content. Bereik "Alleen gemarkeerde producten" wordt standaardsoort Geen vinkje; gemarkeerde producten krijgen Digitale content en de gekozen categorieën worden de categorieën van digitaal. De eigen teksten worden de teksten van digitaal. `_mm_withdrawal_waiver_applies` blijft staan maar wordt niet meer gebruikt. Een versievlag in de optie zorgt dat dit maar één keer gebeurt.
- `uninstall.php` ruimt ook `_mm_withdrawal_type` op. `_mm_withdrawal_waiver` en `_mm_service_consent` op bestellingen blijven altijd staan.

### 1.7.0

- AI-bots: nieuwe instelling "AI-crawlers buiten de cache houden" op het AI-bots-scherm, standaard uit. Op LiteSpeed krijgt een crawler anders een gecachete pagina zonder dat PHP draait, en telt de module structureel te laag. Aan zet de toolkit een blok `# BEGIN Mediamora AI-bots` bovenaan `.htaccess` waarmee LiteSpeed de cache overslaat voor de user agents van de module, behalve Googlebot en Bingbot (die blijven gecachet en tellen dus nog te laag). Kost meer serverbelasting. Alleen op LiteSpeed; het blok verdwijnt bij uitzetten van de instelling of de module, bij deactiveren en bij verwijderen, en wordt net als het preview-blok teruggelezen en zo nodig hersteld. Een CDN vóór de server (QUIC.cloud, Cloudflare met paginacache) kan nog steeds een kopie geven.
- AI-bots: een bezoek hoogt alleen de teller op en schrijft weg; sorteren gebeurt pas op het AI-bots-scherm. Het opgeslagen pad komt uit `REQUEST_URI` via `wp_unslash` en `wp_parse_url`, zonder querystring en stuurtekens en hooguit 190 tekens. Een pad als `//voorbeeld.nl/x` wordt niet meer als host gelezen.
- Preview-link: slaat een preview-bezoek de onderhoudsmodus van Elementor over, dan krijgt de pagina altijd noindex, nofollow: via `wp_robots`, via het filter `rank_math/frontend/robots` (Rank Math negeerde `wp_robots`) en via een header `X-Robots-Tag: noindex, nofollow` voor andere SEO-plugins. Zonder onderhoudsmodus en voor gewone bezoekers verandert er niets; een oud preview-cookie na de lancering geeft dus geen noindex meer.
- Updater: vergelijkt met `Version:` in de kop van de plugin, dezelfde bron als WordPress, in plaats van met `MM_TOOLKIT_VERSIE`.
- Updater: GitHub wordt alleen aangeroepen in de beheeromgeving, in WP-cron, via WP-CLI en wanneer WordPress zelf op updates controleert (ook als MainWP dat vanaf de voorkant start). Andere verzoeken wachten dus nooit meer op GitHub. De laatst opgehaalde release wordt bewaard in `mm_toolkit_release_laatst`, zodat een update niet uit de lijst verdwijnt als GitHub even niet antwoordt.
- Updater: een map van een afgebroken update in de werkmap van de upgrader laat het terugzetten van de mapnaam niet meer mislukken.
- De code voor `.htaccess` staat nu in `includes/htaccess.php` en wordt gedeeld door de preview-link en AI-bots.

### 1.6.0

- Nieuwe module Herroepingsrecht bij afrekenen (`withdrawal_waiver`), standaard uit en alleen aan te zetten als WooCommerce actief is. Zet een verplicht vinkje boven de bestelknop waarmee de koper instemt met directe toegang tot een online cursus en afziet van het herroepingsrecht. Markup en foutweergave zijn gelijk aan die van het voorwaardenvinkje van WooCommerce. Werkt met de klassieke checkout en de Checkout-widget van Elementor; gebruikt de afrekenpagina het checkoutblok, dan staat er een melding in de instellingen.
- Herroepingsrecht: de letterlijke vinkjestekst komt op de bestelling in `_mm_withdrawal_waiver`, dezelfde metakey als de oude `functions.php`-code, en staat in de bestelmails en onder het factuuradres in het beheer. Werkt met en zonder HPOS.
- Herroepingsrecht: bereik "Alle producten" of "Alleen gemarkeerde producten". Een product telt mee via het nieuwe vinkje Herroepingsrecht in de tab Algemeen, of via een gekozen productcategorie (ook via een subcategorie). Bij variaties telt het hoofdproduct.
- Herroepingsrecht: eigen teksten voor het vinkje, de foutmelding en het label in mail en beheer. Leeg geeft de standaardtekst in het Nederlands (site-taal `nl_*`) of Engels.
- Herroepingsrecht: staat de oude code nog in `functions.php` (functie `mm_withdrawal_text`), dan doet de module niets en staat er een melding in de instellingen. Hij neemt het over zodra die code weg is.
- Herroepingsrecht: het vinkje blijft aangevinkt als de afrekenpagina ververst, bijvoorbeeld na het wijzigen van het land.
- Modules kunnen een plugin vereisen. Zonder die plugin is de schakelaar grijs, laadt de module niet en blijft bij opslaan de keuze staan die er al was.
- `uninstall.php` ruimt de instellingen van Herroepingsrecht en het productvinkje `_mm_withdrawal_waiver_applies` op. `_mm_withdrawal_waiver` op bestellingen blijft altijd staan: dat is het bewijs van afstand.

### 1.5.0

- Hero-preload werkt nu ook op archieven: winkel, productcategorieën, blog en zoekresultaten. De hero staat daar in een archieftemplate van de Elementor Theme Builder. Welk template dat is, bepaalt Elementor Pro zelf via zijn conditions manager, dus het is precies het template dat op die pagina wordt getoond, ook bij aparte templates per categorie. Zonder Elementor Pro of zonder passend template gebeurt er op archieven niets.
- Hero-preload: achtergronden via een dynamic tag, bijvoorbeeld de categorieafbeelding of de uitgelichte afbeelding, worden nu ook gepreload. De url staat dan niet in de Elementor-data; de module laat Elementor de tag oplossen, net als bij het renderen. Staat er een tag, dan gaat die voor op een vaste afbeelding die nog in de data staat, zoals Elementor zelf ook doet. Dat geldt voor een gewone achtergrond en voor een dynamische slideshow-galerij. Lukt het oplossen niet, dan komt er geen preload.
- Hero-preload: de Elementor-data wordt nu als JSON gelezen in plaats van met een zoekpatroon. De volgorde is gelijk gebleven, dus op pagina's verandert de uitkomst niet.
- Hero-preload: bij een slideshow gebruikt de verfregel op een archief het template-ID in de selector.
- Hero-preload: nieuw filter `mediamora_hero_preload_bron` om de bron zelf te bepalen. Krijgt de gevonden bron mee (of null) en verwacht een array met `id` en `data` (de Elementor-data als JSON).

### 1.4.0

- Anti-spam: de rapportmail en de bijna-weigeringsmail gaan via WP-cron in plaats van tijdens de inzending, zodat een bezoeker bij het versturen van een formulier niet meer op de mail wacht. Er wordt een eenmalig event ingepland, alleen als de periode om is en er nog geen klaarstaat. Inhoud en frequentie van beide mails blijven gelijk.
- Vastgezette modules (via `wp-config.php`) behouden hun opgeslagen waarde bij het opslaan van het instellingenscherm. Voorheen werd daar false weggeschreven, omdat de grijze schakelaar niet wordt meegestuurd.
- Nieuwe constantennamen om een module vast te zetten: `MM_TOOLKIT_MODULE_<SLEUTEL>`, zodat ze niet kunnen botsen met de eigen constanten van de toolkit. De oude namen `MM_TOOLKIT_<SLEUTEL>` blijven werken voor de bestaande modules.
- `uninstall.php` ruimt bij verwijderen alle opties, logs, cron-events, alt-kenmerken (`_mm_alt_*`) en het `.htaccess`-blok op, behalve voor modules waarvan de losse versie nog op de site staat. Zie "Deactiveren en verwijderen".
- Deactiveren haalt de cron-events weg.

### 1.3.0

- Preview-link: op LiteSpeed slaat de cache verzoeken met het preview-cookie over, zodat klanten ook op al gecachete pagina's de site zien in plaats van de onderhoudspagina. De toolkit zet daarvoor een blok `# BEGIN Mediamora Preview` bovenaan `.htaccess`, alleen op LiteSpeed en alleen zolang de module aan en de onderhoudsmodus van Elementor aan staat. Gaat de module of de onderhoudsmodus uit, of wordt de plugin gedeactiveerd, dan verdwijnt het blok weer. Na elke schrijfactie wordt `.htaccess` teruggelezen; klopt het niet, dan komt de vorige inhoud terug.
- Updater: een update wordt alleen aangeboden als het pakket van `api.github.com` of `codeload.github.com` komt en bij de repo `hettyvanderwal/mediamora-toolkit` hoort.
- Updater: `Update URI` in de plugin-header, zodat WordPress nooit een plugin met dezelfde slug van wordpress.org als update aanbiedt.
- Formuliermonitor: de testmelding gaat via de link "Testmelding sturen" onder Instellingen > Mediamora Toolkit en vraagt een nonce. Het losse adres `/wp-admin/?mm_monitor_test=1` verstuurt zelf niets meer, maar toont een knop met een geldige link.

### 1.2.0

- Anti-spam: de logmap krijgt een willekeurige naam en wordt afgeschermd voordat er logs naartoe verhuizen.
- Anti-spam: `debug.txt` valt onder de bewaartermijn en verdwijnt als debug uitgaat.
- Anti-spam: links in gewone tekstvelden worden niet meer bij de eerste geweigerd. Nieuwe instelling "Max. links in een gewoon tekstveld", standaard 1. E-mailadressen tellen niet meer mee als link.
- AI-bots: het scherm staat nu onder Instellingen in plaats van Gereedschap. De oude URL stuurt door.
- REST-gebruikers afschermen staat standaard uit, behalve op webshops (WooCommerce aan).

## Release maken

1. Versie ophogen op twee plekken in `mediamora-toolkit.php`: `Version:` in de kop en `MM_TOOLKIT_VERSIE`. Of een update wordt aangeboden hangt alleen af van `Version:` in de kop; de constante wordt gelijk gehouden voor de rest, zoals de mailheader van de Formuliermonitor.
2. Bestanden uploaden naar de repo.
3. Release aanmaken met tag gelijk aan de versie, met een v ervoor, bijvoorbeeld `v1.0.1`.

Sites zien de update binnen twaalf uur in de updatelijst en in MainWP.
