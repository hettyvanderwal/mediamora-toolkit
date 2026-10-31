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
| Afbeeldingen: ruimte en voorrang | aan, doet niets zonder Elementor | testcode in `functions.php` (`mm_ig_elementor_img_attrs`) |
| Elementor: oude versies tegenhouden | aan, doet niets zonder Elementor | nieuw |
| Verweesde meta opruimen | aan | nieuw |
| Instagram-feed | uit, per site aanzetten | nieuw |

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

Afbeeldingen: ruimte en voorrang vervangt de testcode `mm_ig_elementor_img_attrs` in `functions.php` (intens-gezond.nl). Die functie heeft een vaste naam, maar `functions.php` laadt na de plugins. Daarom staat hij in de lijst als `thema_merkteken` en niet als `merkteken`: de module laadt gewoon, kijkt in zijn eigen filters of de functie bestaat en doet dan niets. Na `after_setup_theme` telt `mm_toolkit_status()` de testcode als losse versie (`los`: "de testcode in functions.php", `laden`: false), zodat het instellingenscherm en de melding bovenaan het beheer hem tonen. Zodra het testblok weg is, neemt de module het over. De handmatige class `skip-lazy` die de testversie per widget nodig had, mag daarna van de widgets af.

REST-gebruikers afschermen ging van standaard aan naar standaard uit. Sites die daar nog geen keuze voor hadden opgeslagen, krijgen die eenmalig alsnog, maar alleen als WooCommerce aanstaat: een webshop houdt de module zo aan. Op een site zonder WooCommerce komt de module uit te staan en is ASE met "Disable REST API" weer de route.

## Elementor: oude versies tegenhouden

Elementor kijkt bij opslaan niet of een document sinds het laden is veranderd. Een tabblad dat al een tijd open staat, overschrijft dus alles wat er intussen is gebeurd: werk uit een ander tabblad, van een andere gebruiker of via Novamira, REST of WP-CLI. De post lock van WordPress helpt niet: die vervalt na 150 seconden zonder heartbeat (laptop in slaap), geldt niet tussen tabbladen van dezelfde gebruiker en wordt server-side niet gezet of gecontroleerd.

De module geeft elk document dat de editor laadt een vingerafdruk mee in zijn config (`mm_vingerafdruk`, via `elementor/document/config`): een md5 over de ruwe waarden van `_elementor_data` en `_elementor_page_settings`, rechtstreeks uit de database. Het tabblad stuurt bij elke eigen aanvraag de header `X-MM-Vingerafdruk` mee (`id:hash,id:hash`, per geladen document). Bij `save_builder` van het document zelf vergelijkt de module die in `elementor/document/before_save` met de database; wijkt hij af, dan weigert Elementor de opslag voordat er iets geschreven is. De editor toont dan een dialoog: "Deze pagina is intussen aangepast, door iemand anders of in een ander tabblad. Herlaad de pagina voordat je opslaat. Wat je nu nog niet hebt opgeslagen, kun je eerst kopiëren." Er is geen knop om toch op te slaan. In de browserconsole staat welk document en welke vingerafdrukken (bij laden en in de database), en de response heeft de header `X-MM-Vingerafdruk-Geweigerd` (`id:geladen:database:soort`).

- Na elke geslaagde opslag leest de module de vingerafdruk opnieuw uit de database en geeft hem terug (response-header en `elementor/documents/ajax_save/return_data`), zodat opslaan in hetzelfde tabblad gewoon blijft werken, ook als Elementor bij het opslaan nog normaliseert.
- Doorschuiven: schrijft een aanvraag van het tabblad zelf naar `_elementor_data` of `_elementor_page_settings` van een document dat het tabblad kent, en kwam de database vlak daarvoor overeen met wat het tabblad kende, dan krijgt het tabblad na elke schrijfactie de nieuwe vingerafdruk. Meerdere schrijfacties in één aanvraag ketenen. Zo geven een nieuwe globale kleur of typografie (REST naar de kit), het opruimen van verwijderde global classes en hooks als `save_post` geen onterechte weigering. Een ander tabblad kent de oude waarde en wordt wel tegengehouden.
- De kit schuift voorzichtiger door. Een nieuwe globale kleur of typografie gaat via REST (`globals/colors` en `globals/typography`): Elementor leest de kit daarvoor uit de database en voegt alleen dat ene item toe. Alleen het commando `globals/.../create` van de kleurkiezer zet het item ook in het kitdocument in het geheugen, en dan alleen met Site-instellingen open. Het script neemt de nieuwe vingerafdruk van de kit daarom pas over als het item met dat `_id` in `custom_colors` of `custom_typography` van de geladen kit staat. Gaat het er buitenom (bijvoorbeeld een kale `$e.data.create`), dan blijft de oude waarde staan en wordt opslaan van de kit geweigerd, in plaats van dat de nieuwe kleur stil verdwijnt. Andere schrijfacties naar de kit buiten zijn eigen opslag schuiven in het tabblad niet door; de eigen opslag van de kit wel.
- Autosave: schrijft Elementor naar een autosave-revisie (document gepubliceerd of privé, ook bij Voorbeeld), dan controleert de module niets. Bij een concept of een document in afwachting schrijft de autosave het document zelf; die telt als opslaan. Een geweigerde autosave meldt de dialoog per document één keer.
- Stuurt een tabblad geen vingerafdruk mee, zoals een tabblad dat al open stond voordat de module aanging, dan wordt het nooit tegengehouden.
- Wijzigingen buiten de editor worden niet tegengehouden, maar wel opgemerkt bij de volgende opslag vanuit een al geopend tabblad. Dat geldt ook voor wijzigingen die Elementor zelf buiten de editor doet, zoals een databasemigratie na een update of de sitetitel die onder Instellingen verandert (die staat ook in de kit).

## Verweesde meta opruimen

Sinds 22 oktober 2026 draait in MainWP (Maintenance-extensie) elke dinsdag rond 05:00 een database-opschoning op de klantsites: 20 revisies bewaren, automatische concepten, spam- en prullenbakreacties en verlopen transients weg. MainWP Child 6.3 doet dat met kale `DELETE`-queries op `wp_posts` en `wp_comments` (`maintenance_db()` en `maintenance_delete_revisions()` in `class-mainwp-child-maintenance.php`), zonder `wp_delete_post_revision()` of `wp_delete_comment()`. De meta van die revisies en reacties blijft daardoor achter. Op karinedebruijn.com was dat na de eerste ronde 40.968 regels in `wp_postmeta`, 90 MB, waarvan 88 MB `_elementor_data`. Het schema blijft in MainWP, omdat het dan in de Pro Reports komt; deze module ruimt erachteraan op.

- Wanneer: elke donderdag vanaf 05:00 in de tijdzone van de site (WP-cron `mm_meta_opruimen_wekelijks`, `weekly`), met per site een vaste verschuiving van 0 tot 59 minuten uit een hash van `home_url()`. Het event wordt ingepland zodra de module laadt en het er nog niet staat: na de update, na de schakelaar of na opnieuw activeren. Bij het aanzetten wordt niet meteen opgeruimd; de eerste ronde is de eerstvolgende donderdag. "Nu tellen" werkt direct.
- Waarom donderdag: MainWP verwerkt de sites in batches, dus niet elke site is dinsdag aan de beurt. Op mediamora.nl kwam de opschoning pas woensdag om 10:05 langs, dus na een ronde op woensdag 05:00; de verweesde meta bleef dan een week staan. Staat het event nog op een woensdag (van een dev-build van 1.14.0), dan plant de module het bij het laden om naar de eerstvolgende donderdag.
- Wat weggaat: regels in `wp_postmeta` waarvan de `post_id` niet in `wp_posts` staat, en regels in `wp_commentmeta` waarvan de `comment_id` niet in `wp_comments` staat. Id 0 blijft in beide tabellen staan.
- Wat alleen geteld wordt: regels in `wp_term_relationships` waarvan de `object_id` niet in `wp_posts` staat, behalve taxonomie `link_category` (die hoort bij `wp_links`). Een regel waarvan ook de term_taxonomy weg is, telt mee.
- Hoe: de module loopt de tabel op `meta_id` door in vensters van 50.000 id's. Per venster haalt een `LEFT JOIN` hooguit 5.000 verweesde id's op, die daarna met `DELETE ... WHERE meta_id IN (...)` weggaan (met de koppeling nog eens in de `WHERE`). Na elke `DELETE` 0,2 seconde pauze. Een ronde heeft 15 seconden; is die op, dan gaat een vervolgronde (`mm_meta_opruimen_vervolg`) zes minuten later verder bij het venster waar hij was. Die vervolgronde staat al bij het begin van elke ronde klaar, zodat ook een ronde die PHP halverwege afkapt wordt afgemaakt. Een slot (`mm_meta_opruimen_slot`, vervalt na vijf minuten) voorkomt twee rondes tegelijk. De ronde loopt tot de hoogste `meta_id` bij de start.
- Nooit `OPTIMIZE TABLE`: dat zet een lock op de tabel. De vrijgekomen ruimte wordt weer gebruikt en de dump in de back-up wordt meteen kleiner.
- Uitkomst: in de optie `mm_meta_opruimen_laatste`: `status` (`bezig`, `klaar` of `fout`), `compleet`, `start` en `eind`, `rondes` (in hoeveel delen), `fout`, per tabel het aantal verwijderde regels en de bytes (lengte van `meta_key` plus `meta_value`, dus een schatting zonder indexen) en `termen`. Onder Instellingen > Mediamora Toolkit staat een korte regel bij de module en een overzicht onder de tabel, met de knop **Nu tellen**. Die telt in dezelfde vensters wat een ronde zou weghalen (hooguit 20 seconden, anders staat er "onvolledig") en bewaart dat in `mm_meta_opruimen_telling`. Hij verwijdert niets.
- Uitzetten (schakelaar, `MM_TOOLKIT_MODULE_META_OPRUIMEN` of deactiveren) haalt de events weg en breekt een lopende ronde af; de uitkomst van de laatste ronde blijft staan. Gaat de module weer aan, dan plant hij de eerstvolgende donderdag weer in.

## Instagram-feed

Laat de laatste Instagram-posts van de klant zien met de shortcode `[mm_instagram]`, bijvoorbeeld in een Shortcode-widget in de footer. De gegevens komen uit een JSON-feed van [Behold](https://behold.so). De klant koppelt haar Instagram-account zelf via de koppellink van Behold (account moet zakelijk of creator zijn); in de module komt alleen de feed-URL.

Waarom niet de widget van Behold: die laadt bij elke bezoeker een script en de foto's van Behold, en elke paginaweergave telt dan als view. Het gratis plan heeft 1.200 views per maand en pauzeert het account tot de volgende maand als die op zijn. In de footer is dat snel bereikt.

- Ophalen: WP-cron `mm_instagram_ophalen`, `twicedaily`, met per site een vaste verschuiving uit een hash van `home_url()`. Alleen ingepland als er een feed-URL is. De feed van Behold ververst op het gratis plan één keer per dag, dus vaker heeft geen zin. Opslaan met een nieuwe URL of een ander aantal haalt meteen op; daarnaast is er de knop **Nu ophalen**.
- Alleen `https://feeds.behold.so/<id>` als feed-URL. Foto's alleen van `behold.pictures`, `cdninstagram.com` en `fbcdn.net`, en alleen als `getimagesize()` er een JPEG, PNG of WebP in ziet.
- Foto's: de versies `small` en `medium` van Behold (400 en 700 px hoog of breed, vierkant of 4:5), in `uploads/mediamora-instagram/<post-id>-klein|middel.<ext>`. Zonder `sizes` de originele afbeelding (bij video de omslag). Een foto die er al staat wordt niet opnieuw gedownload. Foto's van posts die uit de feed vallen blijven nog 14 dagen staan, zodat pagina's in een cache geen kapotte afbeelding tonen; daarna gaan ze weg bij de volgende ophaalronde.
- Bij een fout (Behold onbereikbaar, account gepauzeerd, lege feed) blijft de vorige feed staan en komt de fout op het instellingenscherm en in de moduletabel. Lukt één foto niet, dan valt alleen die post weg.
- Views bij Behold: elke ophaalactie telt als één view, dus zo'n 60 per maand. Bezoekers laden niets van Behold of Instagram: geen externe scripts, geen cookies. Het scherm telt de aanvragen per maand (laatste zes maanden).
- Shortcode: `aantal` (standaard alles), `kolommen` (3), `kolommen_tablet` en `kolommen_mobiel` (standaard gelijk aan de maat erboven), `ruimte` (8 px), `afronding` (0 px) en `verhouding` (`auto`: volgt de eerste foto, want Behold levert vaak 4:5; of bijvoorbeeld `1/1`). De breekpunten komen uit Elementor. Een `<ul class="mm-instagram">` met per post een link naar Instagram (nieuw tabblad) en een afbeelding met `srcset`, `width`/`height`, `loading="lazy"` en als alt-tekst de eerste regel van het bijschrift (hooguit 120 tekens). Kleuren, titel en knop eromheen bouw je in Elementor met de globals van de kit.
- Cache: de shortcode zet de LiteSpeed-tag `mm_instagram`. Is de feed na het ophalen veranderd, dan wordt alleen die tag gepurged. De Shortcode-widget van Elementor valt niet onder de element cache.
- Opslag: `mm_instagram_instellingen` (url en aantal), `mm_instagram_feed` (de posts, autoload) en `mm_instagram_status` (laatste poging, fout, teller, oude foto's).
- Uitzetten (schakelaar, `MM_TOOLKIT_MODULE_INSTAGRAM` of deactiveren) haalt het cron-event weg; feed en foto's blijven staan, maar de shortcode toont niets meer zolang de module uit staat.

In `wp-config.php`, bijvoorbeeld op een academie:

```php
define( 'MM_TOOLKIT_MODULE_ALT_TEKSTEN', false );
```

Beschikbaar: `MM_TOOLKIT_MODULE_ALT_TEKSTEN`, `MM_TOOLKIT_MODULE_HERO_PRELOAD`, `MM_TOOLKIT_MODULE_PREVIEW_LINK`, `MM_TOOLKIT_MODULE_ANTI_SPAM`, `MM_TOOLKIT_MODULE_FORMULIERMONITOR`, `MM_TOOLKIT_MODULE_AI_BOTS`, `MM_TOOLKIT_MODULE_REST_USERS`, `MM_TOOLKIT_MODULE_WITHDRAWAL_WAIVER`, `MM_TOOLKIT_MODULE_POPUP_TIK`, `MM_TOOLKIT_MODULE_JQUERY_WACHT`, `MM_TOOLKIT_MODULE_QUIC_CLOUD`, `MM_TOOLKIT_MODULE_SCHEMA_BASIS`, `MM_TOOLKIT_MODULE_AFBEELDING_RUIMTE`, `MM_TOOLKIT_MODULE_ELEMENTOR_VERSIE`, `MM_TOOLKIT_MODULE_META_OPRUIMEN`, `MM_TOOLKIT_MODULE_INSTAGRAM`.

De oude namen zonder `MODULE_` (zoals `MM_TOOLKIT_ALT_TEKSTEN`) werken nog voor de eerste zeven modules, zodat bestaande regels in `wp-config.php` niet aangepast hoeven te worden. Staan beide er, dan wint de nieuwe naam. Een vastgezette module houdt bij het opslaan van het instellingenscherm de keuze die er al stond.

## Deactiveren en verwijderen

Deactiveren haalt alleen de cron-events (ook die van Verweesde meta opruimen en de Instagram-feed) en de blokken van de toolkit uit `.htaccess` weg. Instellingen en logs blijven staan.

Verwijderen via Plugins ruimt alles op: de opties en transients van de toolkit en de modules, de cron-events, de alt-tekstkenmerken (`_mm_alt_*`, de alt-teksten zelf blijven staan), de productmeta van Herroepingsrecht (`_mm_withdrawal_type` en het oude vinkje `_mm_withdrawal_waiver_applies`; de teksten `_mm_withdrawal_waiver` en `_mm_service_consent` op bestellingen blijven altijd staan, dat is het bewijs van de instemming), de opties van Verweesde meta opruimen, de opties en de fotomap `uploads/mediamora-instagram` van de Instagram-feed, de `.htaccess`-blokken en de logmap van de anti-spam met de inzendingen erin. Kan de logmap niet worden weggehaald, dan blijft de optie `mediamora_antispam_log_dir` staan, zodat te vinden is waar hij staat. Staat de losse versie van een module nog op de site, als plugin of mu-plugin en actief of niet, dan blijft alles van die module staan, want de losse versie gebruikt dezelfde gegevens.

## Wijzigingen

### Nog niet uitgebracht

- Formuliermonitor: de melding noemt het onderwerp van de mislukte mail niet meer, want daar staat vaak een klantnaam of ordernummer in. In plaats daarvan staat er het domein van de ontvanger (`Ontvanger: @gmail.com`), niet het volledige adres. Site, tijdstip, aantal vandaag en de melding van de server blijven. Ook `mm_monitor_log` bewaart het onderwerp niet meer; oudere regels verliezen het bij de eerstvolgende fout.
- Anti-spam: text domain `mediamora` vervangen door `mediamora-toolkit`, gelijk aan de kop van de plugin.

### 1.15.0

- Nieuwe module Instagram-feed (`instagram`), **standaard uit**. Toont de laatste Instagram-posts met `[mm_instagram]`, uit een JSON-feed van Behold die de site twee keer per dag zelf ophaalt, met de foto's op de eigen server. Zo tellen bij Behold alleen de ophaalacties als views en laden bezoekers niets extern. Instellingen onder Instellingen > Instagram-feed (feed-URL, aantal posts, status, Nu ophalen). Zie "Instagram-feed". Eerst ingezet op Traditierijk (footer).
- `uninstall.php` ruimt de opties, het cron-event en de map `uploads/mediamora-instagram` op.

### 1.14.0

- Nieuwe module Verweesde meta opruimen (`meta_opruimen`), **standaard aan** op alle sites, ook op sites die de update krijgen. Uit te zetten met de schakelaar of `MM_TOOLKIT_MODULE_META_OPRUIMEN`. Haalt elke donderdag regels uit `wp_postmeta` en `wp_commentmeta` weg waarvan de post of reactie niet meer bestaat, zoals na de database-opschoning van MainWP, die de sites in batches vanaf dinsdag verwerkt. Na het aanzetten of de update wordt niets meteen opgeruimd: de eerste ronde is de eerstvolgende donderdag, en tot die tijd laat Nu tellen zien wat er weg gaat. In batches met een tijdsbudget en vervolgrondes, zonder `OPTIMIZE TABLE`. Verweesde `wp_term_relationships` worden alleen geteld. Uitkomst en de knop Nu tellen op het instellingenscherm. Zie "Verweesde meta opruimen".
- Het instellingenscherm kan onder de moduletabel een overzicht van een module tonen, met een eigen formulier.
- Een wekelijks event dat een dev-build van 1.14.0 op woensdag had ingepland, gaat bij het laden over naar de eerstvolgende donderdag.
- `uninstall.php` ruimt `mm_meta_opruimen_laatste`, `mm_meta_opruimen_telling`, `mm_meta_opruimen_slot` en de twee cron-events op.

### 1.13.0

- Nieuwe module Elementor: oude versies tegenhouden (`elementor_versie`), **standaard aan** op alle sites, ook op sites die de update krijgen. Uit te zetten met de schakelaar of `MM_TOOLKIT_MODULE_ELEMENTOR_VERSIE`. Weigert opslaan vanuit een Elementor-tabblad als het document sinds het laden is veranderd, met een dialoog om eerst te herladen. Werkt voor pagina's, berichten, Theme Builder-templates, popups, header, footer en de kit, elk met een eigen vingerafdruk. Zie "Elementor: oude versies tegenhouden".
- Geen post lock, geen logging, geen opties of tabellen: `uninstall.php` hoeft er niets voor op te ruimen. Op de voorkant doet de module niets; de schrijfhooks komen er alleen bij als een aanvraag de header `X-MM-Vingerafdruk` meestuurt.

### 1.12.0

- Nieuwe module Afbeeldingen: ruimte en voorrang (`afbeelding_ruimte`), standaard aan. Met een eigen uitsnede (image_size custom, zoals 1000x1333) zet Elementor het bestand in `uploads/elementor/thumbs` en geeft de `<img>` geen width en height. Zolang EWWW Lazy Load alles lazyloadt valt dat niet op, maar met `ewww_image_optimizer_ll_abovethefold` op 2 krijgen de bovenste afbeeldingen geen tijdelijke PNG en verspringt de pagina (CLS 0,17 tot 0,28 op intens-gezond.nl). Twee onderdelen, allebei op `elementor/image_size/get_attachment_image_html`:
  - Afmetingen: heeft de `<img>` geen width en wijst de src naar de eigen uploadsmap, dan komen width en height uit het bestand (`wp_getimagesize`). Heeft hij al een height, dan komt alleen width erbij. Een `<img>` die al width heeft blijft ongewijzigd. Externe url's, SVG's, data-uri's, paden met `../` en bestanden die niet bestaan of kapot zijn worden overgeslagen. http en https maken voor de vergelijking met de uploads-url niet uit, een querystring wordt genegeerd. De maat wordt per pad onthouden binnen het verzoek, ook als er niets uit kwam.
  - Voorrang: afbeeldingswidgets (ook de uitgelichte afbeelding van Elementor Pro) in het eerste top-level element van het hoofddocument krijgen `loading="eager"` (een bestaande loading gaat eraf, anders zet WordPress er later weer lazy op) en de class `skip-lazy` voor EWWW. Het hoofddocument is het single- of archive-template van de Theme Builder als Elementor Pro er een rendert (bepaald via de conditions manager, net als Hero-preload), anders op een pagina of bericht de pagina zelf. Header, footer, popups en loop-items hebben een eigen document-ID en blijven ongewijzigd; op een pagina met single-template ook de eigen inhoud van de pagina. Lager op de pagina houden afbeeldingen hun lazy load.
- Afbeeldingen: ruimte en voorrang markeert de widgets vooraf op `elementor/frontend/builder_content_data`, het enige filter met de elementboom en het document-ID samen. De markering is de class `mm-afbeelding-voorrang` in `_css_classes`: een geregistreerde control, dus hij komt gegarandeerd terug in de settings die het `<img>`-filter krijgt. Die class staat daardoor ook op de wrapper van de widget, handig bij het nakijken in de broncode.
- Afbeeldingen: ruimte en voorrang zet geen fetchpriority: Hero-preload geeft de achtergrond van de hero al voorrang.
- Na het aanzetten de cache van Elementor (bij Element Caching) en LiteSpeed legen, anders blijft de oude HTML staan.
- Modules kunnen een `thema_merkteken` hebben: een functie uit testcode in `functions.php`. `mm_toolkit_status()` kijkt daar na `after_setup_theme` één keer naar en meldt de testcode dan als losse versie. De gewone merktekens worden daarbij niet opnieuw bekeken, want die definiëren de modules na het laden zelf. Zie "Overstappen".
- De module slaat niets op, dus `uninstall.php` hoeft er niets voor op te ruimen.

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
