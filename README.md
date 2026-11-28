# Mediamora Toolkit

De vaste Mediamora-onderdelen in één WordPress-plugin. Elk onderdeel is een module die per site aan of uit staat onder **Instellingen > Mediamora Toolkit**, verdeeld over tabbladen per functie.

| Module | Tabblad | Standaard | Vervangt |
|---|---|---|---|
| Alt-teksten | Vindbaarheid | aan | mu-plugin `mediamora-alt-teksten.php` |
| Hero-preload | Snelheid | aan | mu-plugin `mediamora-hero-preload.php` |
| Preview-link | Bouw | aan | mu-plugin `mm-preview.php` |
| Anti-spam | Beveiliging | aan | plugin `mediamora-anti-spam-elementor` |
| Formuliermonitor | Mediamora | uit, alleen met Onderhoudsklant | plugin `mediamora-formuliermonitor` |
| AI-bots | Vindbaarheid | uit | mu-plugin `mediamora-ai-bots.php` |
| REST-gebruikers afschermen | Beveiliging | uit | mu-plugin `mediamora-rest-users.php` |
| Herroepingsrecht bij afrekenen | Webshop | uit, alleen met WooCommerce | code in `functions.php` (`mm_withdrawal_text`, `mm_consent_text`) |
| Mobiel menu: vroege tik | Snelheid | aan | testcode in `functions.php` (script `mm-popup-vroege-tik`) |
| jQuery-wachtrem | Snelheid | aan | testcode in `functions.php` (`jQuery.holdReady` achter `jquery-core`) |
| QUIC.cloud en kritieke CSS | Snelheid | uit | testcode in `functions.php` (REST-uitzondering, `mm_ccss_zonder_noscript`) |
| Schema op alle pagina's | Vindbaarheid | aan, doet niets zonder Rank Math | nieuw |
| Afbeeldingen: ruimte en voorrang | Snelheid | aan, doet niets zonder Elementor | testcode in `functions.php` (`mm_ig_elementor_img_attrs`) |
| Lettertype voorladen | Snelheid | uit, per site aanzetten bij layout shift door een webfont | nieuw |
| Elementor: oude versies tegenhouden | Beveiliging | aan, doet niets zonder Elementor | nieuw |
| Verweesde meta opruimen | Mediamora | aan, alleen met Onderhoudsklant | nieuw |
| Instagram-feed | Koppelingen | uit, per site aanzetten | nieuw |
| Tijdelijke accounts | Mediamora | aan, ook zonder Onderhoudsklant | mu-plugin `mediamora-tijdelijke-accounts.php` |
| Afbeeldingstitels verbergen | Vindbaarheid | aan, doet niets zonder Elementor | tijdelijke code in `functions.php` (`mm_verberg_afbeeldingstitel`) |
| Persoon in schema | Vindbaarheid | uit, per site aanzetten en invullen | losse code in `functions.php` (Person-node in `rank_math/json_ld`) |
| Afsluiten op einddatum | Mediamora | uit, aanzetten bij een opgezegd onderhoudspakket, ook zonder Onderhoudsklant | nieuw |
| E-mailadressen afschermen | Beveiliging | uit, per site aanzetten | Email Address Obfuscator van ASE en losse code in `functions.php` |
| Formulieren: nieuwsbrief alleen bij vinkje | Koppelingen | uit, alleen met Elementor Pro | losse code in `functions.php` |

## Tabbladen en Onderhoudsklant

Het instellingenscherm heeft een tabblad per functie: Snelheid, Beveiliging, Vindbaarheid, Webshop, Koppelingen, Bouw en Mediamora. Welk tabblad een module heeft, staat bij die module in `mm_toolkit_modules()` (`tabblad`); de tabbladen zelf in `mm_toolkit_tabbladen()` in `includes/modules.php`. Het actieve tabblad staat in de URL (`?tab=snelheid`), zodat je na opslaan of herladen op hetzelfde tabblad blijft. Zonder of met een onbekende `tab` opent het eerste tabblad.

Het formulier bevat alleen de modules van het tabblad dat open staat, en opslaan verandert alleen die. De keuzes van de andere tabbladen blijven staan zoals ze waren, ook als er nog niets voor was opgeslagen. Optienamen, opgeslagen waarden en de constanten `MM_TOOLKIT_MODULE_<SLEUTEL>` zijn niet veranderd: het is alleen een andere indeling.

Het tabblad Mediamora is er alleen voor ingelogde gebruikers met een e-mailadres op `@mediamora.nl`. Andere beheerders zien het tabblad niet, ook niet in de melding over losse versies bovenaan het beheer, en `?tab=mediamora` opent voor hen gewoon het eerste tabblad. Opslaan wordt ook op de server geweigerd (`Geen toegang.`, 403), net als de knop Nu tellen van Verweesde meta opruimen en de link Testmelding sturen van de Formuliermonitor. Het domein is aan te passen met een filter, bijvoorbeeld in een mu-plugin:

```php
add_filter( 'mm_toolkit_mediamora_domein', function () {
	return 'mediamora.nl';
} );
```

Alleen het domein zelf telt, geen subdomeinen; hoofdletters maken niet uit. Dit houdt beheerders van de klant weg van het tabblad, maar is geen slot: een beheerder kan zelf een gebruiker met een adres op `@mediamora.nl` aanmaken.

Bovenaan het tabblad Mediamora staat de schakelaar **Onderhoudsklant** (optie `mm_toolkit_onderhoudsklant`, `1` of `0`, autoload). Modules met `onderhoud` op `true` laden alleen als die aan staat, wat hun eigen schakelaar of constante ook zegt; de eigen keuze blijft bewaard en geldt weer zodra Onderhoudsklant aan gaat. Nu zijn dat de Formuliermonitor en Verweesde meta opruimen. Ze hebben het label Onderhoud en bij Onderhoudsklant uit de status "Inactief: Onderhoudsklant staat uit". Een module op het tabblad Mediamora zonder `onderhoud` valt niet onder de schakelaar, zoals Tijdelijke accounts. Gaat Onderhoudsklant uit, dan haalt het zelfherstel op de volgende beheerpagina de events van Verweesde meta opruimen weg, net als bij de eigen schakelaar.

- Bestaande sites krijgen de schakelaar bij de update eenmalig aan als de Formuliermonitor daar uitdrukkelijk aan stond: een opgeslagen keuze aan in `mm_toolkit_modules`, of vastgezet met `MM_TOOLKIT_MODULE_FORMULIERMONITOR` (of de oude naam `MM_TOOLKIT_FORMULIERMONITOR`) op `true`. Anders gaat hij uit. Een vastgezette waarde gaat voor de opgeslagen keuze, net als bij het laden: constante `false` en opgeslagen aan geeft dus uit. Een standaardwaarde telt niet mee, ook niet die van de Formuliermonitor zelf.
- Verweesde meta opruimen telt niet mee: die staat standaard aan, dus ook op sites zonder onderhoudspakket. Op een site die uitkomt op Onderhoudsklant uit, stopt die module na de update en haalt het zelfherstel zijn events weg. Zijn opgeslagen keuze, of het ontbreken daarvan, blijft bewaard en geldt weer zodra Onderhoudsklant aan gaat. Zet Onderhoudsklant dus na de update aan op elke onderhoudsklant zonder Formuliermonitor.
- Nieuwe sites krijgen bij het activeren Onderhoudsklant uit, behalve als er al een losse versie van een onderhoudsmodule draait, zoals de losse Formuliermonitor.

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
3. Anders geen vinkje als het product verzonden wordt (`needs_shipping()`, dus niet virtueel): een fysiek product is geen digitale content of dienst. Staat de soort expliciet op het product of de categorie, dan geldt die wel.
4. Anders de standaardsoort: Digitale content (standaard), Dienst of Geen vinkje. Die geldt dus alleen voor producten zonder verzending.

Bij een variatie telt het hoofdproduct, behalve voor de verzending: die komt van de variatie in de winkelmand. Per soort die in de winkelmand zit komt één verplicht vinkje, onder het voorwaardenvinkje en boven de bestelknop; zitten beide soorten erin, dan eerst digitaal en dan dienst. Tonen, valideren, opslaan, mail en beheer gebruiken allemaal dezelfde soortbepaling.

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
- Uitkomst: in de optie `mm_meta_opruimen_laatste`: `status` (`bezig`, `klaar` of `fout`), `compleet`, `start` en `eind`, `rondes` (in hoeveel delen), `fout`, per tabel het aantal verwijderde regels en de bytes (lengte van `meta_key` plus `meta_value`, dus een schatting zonder indexen) en `termen`. Onder Instellingen > Mediamora Toolkit, tabblad Mediamora, staat een korte regel bij de module en een overzicht onder de tabel, met de knop **Nu tellen**. Die telt in dezelfde vensters wat een ronde zou weghalen (hooguit 20 seconden, anders staat er "onvolledig") en bewaart dat in `mm_meta_opruimen_telling`. Hij verwijdert niets.
- Uitzetten (schakelaar, Onderhoudsklant uit, `MM_TOOLKIT_MODULE_META_OPRUIMEN` of deactiveren) haalt de events weg en breekt een lopende ronde af; de uitkomst van de laatste ronde blijft staan. Gaat de module weer aan, dan plant hij de eerstvolgende donderdag weer in.

## Tijdelijke accounts

Accounts van externe partijen, bijvoorbeeld een marketeer die een pixel plaatst, verlopen automatisch, zodat er geen beheerdersaccounts blijven rondslingeren. De module staat op het tabblad Mediamora en standaard aan, maar valt niet onder Onderhoudsklant: hij is ook voor klanten zonder onderhoud.

- Gegevens: user meta `mm_verloopt_op` (timestamp) en `mm_overdragen_aan` (user-ID), gelijk aan de losse mu-plugin.
- Blokkeren: vanaf de verloopdatum geeft `determine_current_user` 0 (cookie, applicatiewachtwoord en REST) en geven `wp_authenticate_user` en `authenticate` de fout "Dit account is verlopen." `authenticate` is nodig omdat een applicatiewachtwoord niet langs `wp_authenticate_user` komt en XML-RPC de gebruiker daarna zelf zet; dat gat zit nog wel in de losse mu-plugin.
- Opruimen: dagelijkse cron `mm_tijdelijke_accounts_opruimen`. Die vernietigt de sessies en verwijdert het account met `wp_delete_user`, met de content naar `mm_overdragen_aan`. Zonder geldige erfgenaam (leeg, niet bestaand of het account zelf) blijft het account geblokkeerd staan, net als een account dat naar een net verwijderd account wijst. Op een multisite haalt `wp_delete_user` het account alleen van deze site af.
- Mail: per ronde één mail met de verwijderde accounts, hun erfgenaam en de beheerders die er daarna nog zijn (iedereen met `manage_options`, dus ook eigen rollen). Zo is te zien of er onderweg een beheerder is bijgekomen. De mail gaat naar de beheerders met een adres op `@mediamora.nl`; is die er niet, dan naar `hetty@mediamora.nl`. Standaard aan, uit te zetten bij de module op het tabblad Mediamora (`mm_tijdelijke_accounts_mail`).
- Velden: **Verloopt op** en **Content overdragen aan** op het profiel, het bewerkscherm en Nieuwe gebruiker, alleen zichtbaar en op te slaan voor `@mediamora.nl`-gebruikers met `manage_options`, met een nonce per account. Een beheerder van de klant kan een account opslaan zonder dat de datum verandert. De datum verloopt om 23:59:59 in de tijdzone van de site; leeg maken haalt de datum en de erfgenaam weg en geeft een geblokkeerd account weer toegang. In de keuzelijst staan alleen gebruikers met `edit_posts`, zodat op een webshop of academie niet alle klanten erin komen. Een eerder gekozen gebruiker blijft staan.
- Accounts met een adres op `@mediamora.nl` krijgen geen verloopdatum. Het opslaan wordt geweigerd, ook als het adres in hetzelfde formulier naar zo'n adres verandert. Dit geldt bij het opslaan in het scherm; meta die op een andere manier is gezet, telt gewoon.
- Multisite: Nieuwe gebruiker loopt daar buiten `edit_user()` om, dus de velden staan daar alleen op het bewerkscherm.
- Overzicht: op het tabblad Mediamora, met naam, rol, verloopdatum en erfgenaam. Een verlopen account staat er als geblokkeerd bij, en een ontbrekende erfgenaam in het rood.
- Uitzetten (schakelaar, `MM_TOOLKIT_MODULE_TIJDELIJKE_ACCOUNTS` of deactiveren) haalt het cron-event weg. Let op: zolang de module uit staat, wordt er ook niemand geblokkeerd. De gegevens blijven staan en gelden weer zodra de module aan gaat.

Overstappen: staat `wp-content/mu-plugins/mediamora-tijdelijke-accounts.php` nog op de site, dan laadt de module niet en krijgen `@mediamora.nl`-gebruikers de melding dat het bestand weg kan (als de module aan staat). De mu-plugin blijft dan gewoon werken. Er draait dus nooit iets dubbel. Is het bestand weg, dan neemt de module het bij het volgende verzoek over met dezelfde meta en hetzelfde cron-event. Zelfherstel, deactiveren en verwijderen laten dat event staan zolang het bestand er is. De velden en het overzicht komen pas nadat het bestand weg is.

## Afbeeldingstitels verbergen

Met een eigen uitsnede (image_size custom, zoals 1000x1333 of 1000x1000) bouwt Elementor de `<img>` zelf op in `Group_Control_Image_Size::get_attachment_image_html()` (`includes/controls/groups/image-size.php`) en zet er `title="<titel uit de mediabibliotheek>"` op. Bezoekers zien bij hover dan de bestandsnaam als tooltip. Met een gewone maat (thumbnail, large, full) gaat het via `wp_get_attachment_image()`, en die zet geen title.

- Hoe: een filter op `elementor/image_size/get_attachment_image_html` dat alleen het title-attribuut uit de `<img>`-tags haalt, met `preg_replace( '/(<img\b[^>]*?)\s+title="[^"]*"/i', '$1', $html )`. Alt, class, loading, width en height, data-attributen en de rest blijven staan; `data-title` ook, want voor `title` moet witruimte staan. Een title op een link om de afbeelding heen blijft ook staan.
- Welke widgets: alles wat via die functie loopt. In Elementor (4.4.0 nagekeken): Afbeelding, Afbeeldingsvak, Testimonial en de afbeelding over een Video. In Elementor Pro (4.3.3): Call to action, Flip box, Sitelogo, Portfolio, Hotspot, Uitgelichte afbeelding (een afgeleide van Afbeelding) en de skins Klassiek en Kaarten van Berichten en Archief-berichten.
- Niet gedekt, en ook niet nodig: Afbeeldingscarrousel, Testimonial-carrousel, Reviews en Media-carrousel zetten zelf geen title. De enige andere plek in Elementor met een title op een `<img>` is de miniatuur van een video-embed (`Embed::get_embed_thumbnail_html()`), alleen in de statische weergave van de Video-widget. Die title is de titel van de video (van YouTube of Vimeo), geen bestandsnaam, en gaat niet langs dit filter.
- De functienaam `mm_verberg_afbeeldingstitel` staat vast en zit in een `function_exists`-check. Op drentz.nl staat een tijdelijke versie met dezelfde naam en check in `functions.php` van het child theme. Die laadt na de plugins en slaat zichzelf dus over zolang de module aan staat; er draait nooit iets dubbel. Daarom geen `merkteken` of `thema_merkteken`: de module zou anders zijn eigen functie als losse versie zien.
- Caches: Elementor bewaart de HTML van widgets in de post meta `_elementor_element_cache`, en LiteSpeed de hele pagina. Bij elke wisseling van de module (schakelaar, `MM_TOOLKIT_MODULE_AFBEELDINGSTITELS`, deactiveren en de update naar 1.19.0, die hem meteen aanzet) gaat `_elementor_element_cache` voor alle documenten weg met `delete_post_meta_by_key()`, en daarna de hele LiteSpeed-cache met `do_action( 'litespeed_purge_all' )` als LiteSpeed Cache actief is. De CSS-bestanden van Elementor blijven staan. Dat gebeurt via zelfherstel op `init` (`mm_toolkit_afbeeldingstitels_herstel()`): de optie `mm_toolkit_afbeeldingstitels_stand` (`1` of `0`, autoload) onthoudt of de module laadde, en bij een verschil worden de caches geleegd. Een site die de module nog nooit aan had, hoeft bij uit niets te legen.

## Lettertype voorladen

Elementor zet Google Fonts lokaal neer (optie `elementor_local_google_fonts` = `1`) in `uploads/elementor/google-fonts/`, met `font-display: swap`. Op mobiel komt het tekstlettertype daardoor pas na de eerste weergave binnen, worden de alinea's opnieuw opgemaakt en verspringt de pagina (CLS). De module zet in `wp_head` (prioriteit 1, na Hero-preload) één regel:

```html
<link rel="preload" href="…/uploads/elementor/google-fonts/fonts/montserrat-49e242af.woff2" as="font" type="font/woff2" crossorigin>
```

Staat standaard uit, ook op sites die de toolkit al hadden: een font-preload deelt de bandbreedte met de CSS en maakt de eerste weergave iets trager (zie de meting hieronder). Aanzetten als mobiele PageSpeed bij Layout shift-oorzaken een webfont uit `uploads/elementor/google-fonts/` aanwijst, en het verspringende element in het tekstlettertype van de kit staat; staat het in het kopletterype, dan ook het vinkje voor het kopletterype. Vóór en na meten en weer uitzetten als FCP of LCP meer verslechteren dan de CLS-winst waard is, vooral op pagina's met een hero-afbeelding als LCP (die concurreert met Hero-preload). Vastzetten kan met `MM_TOOLKIT_MODULE_FONT_PRELOAD`.

- Welk lettertype: de typografie `text` uit de actieve kit (`elementor_active_kit` > `_elementor_page_settings` > `system_typography`), en als daar geen lettertype in staat `secondary`. Alleen een Google Font volgens `\Elementor\Fonts::get_font_type()`; bij een systeem- of eigen lettertype, met lokale fonts uit of met Google Fonts helemaal uit (`elementor_google_font` = `0`) gebeurt er niets.
- Welk bestand: uit `uploads/elementor/google-fonts/css/<familie>.css` (kleine letters, zonder spaties: `montserrat.css`, `kleeone.css`) het `@font-face`-blok waarvan de `unicode-range` met `U+0000-00FF` begint (latin), met de stijl en het gewicht uit de kit. Leeg of `normal` is 400, `bold` 700; een bereik als `100 900` telt ook. Let op de stijl: Elementor zet de cursieve blokken vóór de rechte, en bij een variabel lettertype hebben alle gewichten van een stijl hetzelfde bestand. Op geertjedokter.nl is het rechte latin-bestand van Montserrat `montserrat-49e242af.woff2`, het cursieve `montserrat-76393105.woff2`.
- Eén bestand: meer preloads concurreren met de CSS en de hero-preload. Op het tabblad Snelheid staat bij de module het vinkje "Ook het kopletterype voorladen" (optie `mm_font_preload_kop`, standaard uit); dan komt `primary` erbij, als dat een ander bestand is.
- Cache: de url's staan in de transient `mm_font_preload` (een dag), met een sleutel uit het kit-ID, `post_modified_gmt` van de kit, het vinkje, `elementor_local_google_fonts` en de `filemtime` van de css-bestanden. Genereert Elementor de fonts opnieuw (andere hash in de bestandsnaam), dan schuift de preload vanzelf mee. Bij het opslaan van de kit en bij een andere actieve kit gaat de transient weg.
- Het woff2-bestand moet op schijf staan, anders komt er geen preload (geen 404-preload). De controle gebeurt bij elk verzoek, ook als de url uit de cache komt.
- Alleen op de voorkant: niet in wp-admin, bij REST, AJAX en feeds, en niet in de editor of het voorbeeld van Elementor.
- Status: `mm_toolkit_status()['font_preload']` zoals bij de andere modules (`gewild`, `vast`, `los`, `laden`). In de statuskolom staat welk bestand wordt voorgeladen, of waarom niet.
- LiteSpeed Cache laat de regel ongemoeid: nagekeken op geertjedokter.nl met LiteSpeed 7.9.3, HTML minify en `optm-guest_only` aan, bij een cache-miss en bij hits. Na aanzetten de LiteSpeed-cache legen.
- Wat het oplevert (geertjedokter.nl, 17 nov 2026, Lighthouse 12.8.2 mobiel met echte throttling, mediaan van vier metingen, module om en om uit en aan): CLS van 0,136 naar 0,001, FCP en LCP van 2,64 naar 2,88 s, score van 73 naar 76. De preload deelt de bandbreedte met de CSS, dus de eerste weergave komt iets later; `fetchpriority="low"` op de preload maakte geen verschil. Daar zat de rem in `kleeone.css` van Elementor: 248 `@font-face`-blokken (Japanse subsets), 56 KB over de lijn en render-blocking.

## Persoon in schema

Voor praktijken met één persoon (therapeuten, coaches) of een team (verloskundigen, fysiotherapie, accountants, opleidingsinstituten). De module zet elke persoon als Person in het schema van Rank Math en koppelt die aan de Organization. De gegevens staan op het tabblad Vindbaarheid onder de moduletabel, zodra de module aan staat, met een eigen formulier (`manage_options`, nonce `mm_schema_persoon_opslaan`). Ze komen in de optie `mm_schema_persoon` (autoload), als `personen` (de lijst) en `praktijk`.

Per persoon een blok met deze velden. Met Persoon toevoegen komt er een blok bij; met Omhoog, Omlaag en Verwijderen bepaal je de lijst. Er verandert pas iets bij Gegevens opslaan.

| Veld | In het schema | |
|---|---|---|
| Naam | `name` | verplicht; blokken zonder naam vallen weg bij opslaan |
| Titel vóór de naam | `honorificPrefix` | bijvoorbeeld dr. |
| Titel na de naam | `honorificSuffix` | bijvoorbeeld RA, MSc, PhD |
| Functie | `jobTitle` | |
| Omschrijving van de persoon | `description` | |
| Pagina over de persoon | `url` | bijvoorbeeld de Over-pagina |
| Vakgebieden, één per regel | `knowsAbout` | altijd een lijst |
| Registraties, één per regel: `Naam \| Erkend door \| URL` | `hasCredential` (`EducationalOccupationalCredential`, met `recognizedBy` als `Organization`) | BIG, KNGF, kwaliteitsregisters, NFG, RA; de URL is optioneel en telt alleen met Erkend door |
| Opleidingen, één per regel: `Naam \| URL` | `alumniOf` (`EducationalOrganization`) | de URL is optioneel en telt alleen met een naam |
| Lid van, één per regel: `Naam \| URL` | `memberOf` (`Organization`) | beroepsvereniging of netwerk |
| Talen, één taalcode per regel | `knowsLanguage` | BCP 47 (`nl`, `en`, `nl-BE`); andere regels vallen weg; altijd een lijst |
| Profielen, één URL per regel | `sameAs` | alleen http(s), dubbele regels vallen weg; altijd een lijst |
| Foto uit de mediabibliotheek | `image` | de URL van het origineel |
| Rol bij de praktijk | `founder` of `employee` op de Organization | standaard founder |
| WordPress-gebruiker | | optioneel, zie "Koppeling met de blogschrijver" |

Eén keer voor de hele praktijk: Omschrijving van de praktijk, als `description` op de Organization (vervangt dan wat Rank Math daar zet).

- Uitvoer: per persoon een Person-node met `worksFor` naar het `@id` van de Organization. De eerste persoon in de lijst (met een naam) krijgt `@id` `home_url( '/#persoon' )`, de volgende `home_url( '/#persoon-<slug van de naam>' )`, met `-2`, `-3` erachter als twee personen dezelfde naam hebben. Een andere naam geeft dus een ander `@id`, behalve bij de eerste. Lege velden blijven weg.
- Op de Organization: `founder` en `employee` naar de personen met die rol, als enkele verwijzing (`{"@id": ...}`) bij één persoon en als lijst bij meer. Bij herhaalbare velden met organisaties (`alumniOf`, `memberOf`, `hasCredential`) hetzelfde: één regel geeft een object, meer regels een lijst.
- Waar: in het filter `rank_math/json_ld`, dus overal waar Rank Math de algemene graph zet, ook op de pagina's die Schema op alle pagina's erbij haalt. Het filter wordt pas op `plugins_loaded` gezet en alleen als `RANK_MATH_VERSION` bestaat.
- De Organization wordt gevonden op `@id` `home_url( '/#organization' )`, en anders als de `publisher` van Rank Math zolang die geen Person is. Staat er geen Organization in de graph, of is de site in Rank Math een persoon (`knowledgegraph_type` `person` in `rank-math-options-titles`), dan doet de module niets.
- Niets dubbel: staat er al een node met een van de `@id`'s van de module in de graph, of heeft de Organization al een `founder` of `employee`, dan doet de module niets, ook geen auteurskoppeling. Dat vangt ook losse code met een eigen `@id` (zoals `#naam`). Het filter draait daarom op prioriteit 9999, na Rank Math en na de meeste losse code. Wat de module bij de laatste weergave zag, staat in de optie `mm_schema_persoon_bezet` (`1` of `0`, autoload, alleen geschreven als het verandert); de statuskolom meldt dan "Doet niets: ... waarschijnlijk losse code". Door de paginacache kan die melding een weergave achterlopen. Anders noemt de statuskolom de naam, of het aantal personen.
- Overstappen van losse code: gegevens invullen, module aanzetten, de losse code uit `functions.php` halen, cache legen. Zolang de losse code er staat, blijft die gewoon werken en doet de module niets. Let op: het `@id` van de eerste persoon wordt `#persoon`, ook als de losse code een ander `@id` had.

### Koppeling met de blogschrijver

Rank Math zet op elk bericht en elke pagina een eigen Person voor de schrijver, onder de sleutel `ProfilePage` in `rank_math/json_ld`, met als `@id` de auteurs-URL (`get_author_posts_url()`, met de versleutelde slug van ASE Obfuscate Author Slugs als dat aan staat), of de pagina-URL met `#author` als de auteursarchieven in Rank Math uit staan. Het BlogPosting (of Article, NewsArticle) wijst daarmee als `author` naar die Person. Wijst niets ernaar, zoals op gewone pagina's, dan haalt Rank Math die Person later zelf weg (`remove_person_entity` op `rank_math/schema/validated_data`).

Is een persoon gekoppeld aan een WordPress-gebruiker, dan zoekt de module in de graph op die `@id`'s, niet op de sleutel. De Person van Rank Math gaat eruit, en elke verwijzing naar dat `@id`, ook dieper in de graph, wordt een verwijzing naar het `@id` van de persoon (met de naam van de persoon als de verwijzing een `name` had). Er blijft dan één Person per persoon over. Het `#author`-`@id` telt alleen op een bericht of pagina van die gebruiker; bij een bericht van iemand anders blijft diens Person staan.

Op het auteursarchief van de gebruiker is de Person van Rank Math de `mainEntityOfPage` van de ProfilePage. Daar gaat de Person er ook uit, en krijgt de ProfilePage `mainEntity` naar de persoon.

### Omzetten van 1.20 t/m 1.23

Tot 1.23 stond er in `mm_schema_persoon` één persoon met platte velden (`naam`, `opleiding_naam`, `opleiding_url`, `lid_naam`, `lid_url` en dergelijke). De module leest dat formaat nog steeds en zet het bij het lezen om: één persoon, waarbij opleiding en lidmaatschap de eerste regel van Opleidingen en Lid van worden (ook een losse URL zonder naam blijft bewaard). De uitvoer blijft daarmee letterlijk gelijk aan die van 1.23 zolang er niets nieuws is ingevuld; `tests/schema-persoon.php` vergelijkt dat met de uitvoer van 1.23.0. In het nieuwe formaat opgeslagen wordt pas bij Gegevens opslaan, zodat terug naar 1.23 tot dat moment zonder verlies kan. Na opslaan leest 1.23 de gegevens niet meer.

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

Beschikbaar: `MM_TOOLKIT_MODULE_ALT_TEKSTEN`, `MM_TOOLKIT_MODULE_HERO_PRELOAD`, `MM_TOOLKIT_MODULE_PREVIEW_LINK`, `MM_TOOLKIT_MODULE_ANTI_SPAM`, `MM_TOOLKIT_MODULE_FORMULIERMONITOR`, `MM_TOOLKIT_MODULE_AI_BOTS`, `MM_TOOLKIT_MODULE_REST_USERS`, `MM_TOOLKIT_MODULE_WITHDRAWAL_WAIVER`, `MM_TOOLKIT_MODULE_POPUP_TIK`, `MM_TOOLKIT_MODULE_JQUERY_WACHT`, `MM_TOOLKIT_MODULE_QUIC_CLOUD`, `MM_TOOLKIT_MODULE_SCHEMA_BASIS`, `MM_TOOLKIT_MODULE_AFBEELDING_RUIMTE`, `MM_TOOLKIT_MODULE_ELEMENTOR_VERSIE`, `MM_TOOLKIT_MODULE_META_OPRUIMEN`, `MM_TOOLKIT_MODULE_INSTAGRAM`, `MM_TOOLKIT_MODULE_TIJDELIJKE_ACCOUNTS`, `MM_TOOLKIT_MODULE_AFBEELDINGSTITELS`, `MM_TOOLKIT_MODULE_NIEUWSBRIEF_VINKJE`.

De oude namen zonder `MODULE_` (zoals `MM_TOOLKIT_ALT_TEKSTEN`) werken nog voor de eerste zeven modules, zodat bestaande regels in `wp-config.php` niet aangepast hoeven te worden. Staan beide er, dan wint de nieuwe naam. Een vastgezette module houdt bij het opslaan van het instellingenscherm de keuze die er al stond.

## AI-bots: bezoekers vanuit AI-antwoorden

Naast de crawlers telt de module de mensen die via een AI-antwoord op de site komen: bezoekers met een AI-dienst als verwijzer (`chatgpt.com`, `perplexity.ai`, `gemini.google.com`, `copilot.microsoft.com`, `claude.ai` en een paar andere) of met zo'n dienst in `utm_source` (ChatGPT zet `utm_source=chatgpt.com` achter zijn links). Ze staan bovenaan het AI-bots-scherm, per bron en per landingspagina, in dezelfde maandkeuze als de crawlers.

- Tellen gebeurt in de browser, omdat een bezoeker op LiteSpeed meestal een gecachete pagina krijgt zonder dat PHP draait. Een klein inline script (`#mm-aibezoek`, in de head, met `data-no-optimize` en `data-no-defer` zodat LiteSpeed het niet uitstelt) doet alleen iets bij een bekende AI-bron, en meldt het bezoek dan met `sendBeacon` aan `admin-ajax.php?action=mm_aibezoek`. Geen REST API, want die staat met ASE vaak dicht voor bezoekers.
- Geen cookies, geen sessionStorage en geen IP-adres. Opgeslagen wordt alleen een teller in `mm_aibots_bezoek_JJJJ-MM`: `[bron => [pad => aantal]]`, met dezelfde padenlimiet als de crawlers. Herladen telt daardoor opnieuw. Ingelogde gebruikers krijgen het script niet.
- De server beslist wat telt: alleen een bekende bron, een pagina en geen crawler als user agent. Een melding met een Origin van een ander domein telt niet. Een melding namaken kan wel; dat geeft hooguit een te hoge teller.
- Google AI Overviews en AI Mode komen binnen als gewoon `google.com`-verkeer en zijn hier niet te zien. Die staan in Search Console onder Generatieve AI; Copilot staat daarnaast in Bing Webmaster Tools onder AI Performance.
- Een dienst toevoegen of aanpassen kan op één plek: `mm_aibots_bronnen()` in `modules/ai-bots.php`. Script en server lezen allebei die lijst.
- Opschonen na zes maanden en verwijderen bij uninstall gaan mee met de crawlerlogs.

## Afsluiten op einddatum

Voor een onderhoudsklant die opzegt. Op het tabblad Mediamora vul je één datum in, **Einddatum onderhoud**; op die datum haalt de site zelf de onderhoudsplugins eraf en daarna de toolkit. Er hoeft dan niemand meer in te loggen, dus het werkt ook als de klant het account van Mediamora al eerder heeft weggehaald. De module staat standaard uit en valt niet onder Onderhoudsklant: hij moet ook draaien als die schakelaar intussen uit staat.

**Instellen.** Zet de module aan en vul de datum in onder de moduletabel (optie `mm_einddatum`, `JJJJ-MM-DD`). Leeg betekent niets gepland; leegmaken en opslaan annuleert en wist de voortgang. Een datum van vandaag of eerder wordt pas opgeslagen na een extra vinkje, zodat een tikfout niet meteen alles wist; daarna start de eerste ronde binnen een minuut (bij het eerstvolgende bezoek). Onder het veld staat welke plugins er op de site gevonden zijn en wat er in WPvivid gewist wordt. Alleen voor `@mediamora.nl`-gebruikers, net als de rest van het tabblad.

**Proefdraai.** De knop loopt alles na zonder iets te wijzigen: datum en tijdzone, of de dagelijkse controle is ingepland, `DISABLE_WP_CRON`, de gevonden plugins met versie en stand, de remotes, schema's, Pro-koppeling en geplande taken van WPvivid, of het bestandssysteem `direct` is, en naar welk adres de mail gaat.

**Uitvoeren.** Dagelijkse cron `mm_einddatum_controle` om 00:10 in de tijdzone van de site, alleen zolang er een datum staat. Vanaf de datum, met een transient-slot `mm_einddatum_slot` tegen een dubbele aanroep:

1. WPvivid wissen. Eerst de lokale back-ups: de map onder `wp-content` waar WPvivid ze in zet, `wpvividbackups` of het pad in `wpvivid_local_setting['path']`. Dat pad telt helemaal: bij `a/b` gaat alleen `wp-content/a/b` weg, niet `a` en niet een map `b` direct onder `wp-content`. Geweigerd wordt een absoluut pad, een pad met een `..`-deel, `wp-content` zelf, en een pad waarvan het eerste deel een map van WordPress is (`uploads`, `plugins`, `themes`, `mu-plugins`, `languages`, `upgrade`, `cache`, hoofdletters maken niet uit). Daarna controleert `realpath()` dat de echte map binnen `wp-content` ligt, en het echte pad gaat nog een keer langs dezelfde regels, zodat ook een symbolische link halverwege niet buiten `wp-content` of in `uploads` uitkomt. WPvivid 0.9.140 accepteert zelf alleen een naam zonder slash; een genest pad kan uit een oudere versie of een met de hand aangepaste optie komen. Daar staan de databaseback-ups in, met een kopie van de S3-instellingen. Daarna rechtstreeks in de opties, ook als WPvivid niet actief is: de remotes uit `wpvivid_upload_setting` (alles met een `type`; de S3-sleutels staan daarin), `remote_selected` in `wpvivid_user_history`, de schema's (`wpvivid_schedule_setting`, `wpvivid_schedule_addon_setting`, `wpvivid_incremental_schedules`), de koppeling met het Pro-account (`wpvivid_pro_user`, `wpvivid_connect_key`, `wpvivid_dashboard_info`) en de overzetsleutels (`wpvivid_api_token`, `wpvivid_saved_api_token`). Ook de kopieën van de remote-instellingen gaan eruit: WPvivid zet die per back-up in `wpvivid_backup_list` en in Pro in `wpvivid_new_remote_list` (onder `remote`), en per taak in `wpvivid_task_list` en `wpvivid_exporter_task_list` (onder `remote_options`). Omdat Pro eigen lijsten kan hebben, zoekt de module in alle opties met `wpvivid_` ervoor en in de tabel `wpvivid_options` naar `remote_options` en naar alles wat op een remote lijkt (een `type` met een sleutel, token of wachtwoord). De lijsten zelf blijven staan. Alle geplande taken waarvan de hook met `wpvivid` begint, worden afgemeld, dus ook `wpvivid_main_schedule_event` en `wpvivid_schedule_event<id>` van Pro, plus de taken die als hook het ID van een schema van Pro hebben (ook de incrementele). De namen zijn nagelopen in de broncode van WPvivid Backup Plugin 0.9.140 en in de broncode en database van WPvivid Plugins Pro 2.2.54. Zijn beide WPvivid-plugins weg, dan gaan daarna ook alle andere opties met `wpvivid_` ervoor en de tabel `wpvivid_options` weg.
2. WPvivid Plugins Pro en daarna WPvivid Backup Plugin deactiveren en verwijderen.
3. MainWP Child Reports en daarna MainWP Child deactiveren en verwijderen.
4. Een mail naar `MM_MONITOR_ONTVANGER`, hetzelfde adres als de Formuliermonitor (per site te overschrijven in `wp-config.php`): domein, einddatum, per plugin verwijderd, niet gevonden, alleen gedeactiveerd of mislukt (met de melding), of het wissen in WPvivid gelukt is en wat er gewist is, en als laatste regel dat de site uit het MainWP-dashboard moet en het klantrecord in de Projecten-tool op pakket geen. Eerst via `wp_mail()`, en lukt dat niet via `mail()` van de hosting, zoals de Formuliermonitor.
5. De toolkit deactiveren (de gewone haak ruimt de events en de `.htaccess`-blokken op) en verwijderen; `uninstall.php` ruimt de rest op, ook de opties van deze module. Vooraf kijkt de module of de pluginmap en elke map in de toolkit schrijfbaar zijn. Zo niet, dan blijft de toolkit aan staan en zegt de mail dat je hem zelf moet verwijderen. Mislukt het verwijderen toch, nadat hij zichzelf al heeft uitgezet, dan volgt een aparte mail "toolkit niet verwijderd". Hij gaat dan niet opnieuw aan, want `uninstall.php` heeft zijn instellingen al gewist en hij zou met de standaardinstellingen gaan draaien.

Plugins worden herkend op de naam in de pluginkop, en anders op het vaste bestand (`wpvivid-backup-pro/wpvivid-backup-pro.php`, `wpvivid-backuprestore/wpvivid-backuprestore.php`, `mainwp-child-reports/mainwp-child-reports.php`, `mainwp-child/mainwp-child.php`). Zo wordt ook een plugin in een andere map gevonden.

Elke stap kijkt eerst naar de toestand en kan dus veilig nog een keer draaien; de voortgang staat in `mm_einddatum_stand`. Waren er in de ronde plugins actief, dan zit hun code nog in het geheugen. Daarom gaat het na stap 3 verder in een nieuw verzoek, het event `mm_einddatum_vervolg` twee minuten later: daar draait stap 1 nog een keer, wordt de rest van WPvivid opgeruimd, nagekeken en gemaild.

**Zonder directe schrijftoegang.** Heeft WordPress in de cron geen methode `direct` in `WP_Filesystem` (bijvoorbeeld `FS_METHOD` op `ftpext`), dan worden de plugins alleen gedeactiveerd, blijven de lokale back-ups staan en staat dat in de mail. De toolkit blijft dan staan en probeert het elke nacht opnieuw. Dezelfde uitkomst wordt maar één keer gemaild; verandert er iets, dan volgt een nieuwe mail. Ook als de mail mislukt blijft de toolkit staan: hij verwijdert zichzelf pas als de eindstand gemaild is.

Niet op een multisite. Bewust niet: de achtergebleven gegevens van MainWP Child (opties `mainwp_*`, tabellen `mainwp_child_changes_*`, `mainwp_stream`), het leegmaken van de S3-map en de snapshot-tabellen van WPvivid. Let op: elke back-up die al in de bucket staat, bevat een kopie van de S3-instellingen van dat moment. Maak bij de offboarding dus de map van de klant in de bucket leeg.

## Anti-spam: velden uitzonderen

Onder **Instellingen > Mediamora Anti-Spam** staat het tekstveld "Velden per formulier" (`exempt_fields` in `mediamora_antispam_settings`, standaard leeg). Per regel één formulier:

```
94c5fa7: field_c5fc26d, field_946b13c
04f3906: *
```

Het formulier-ID is het element-ID van de formulierwidget, wat `$record->get_form_settings( 'id' )` teruggeeft. Het staat in de broncode als het verborgen veld `form_id` en als `data-id` op de widget; het is niet het veld "Formulier-ID" onder Extra opties. De veld-ID's zijn de `custom_id`'s, in Elementor bij het veld onder Geavanceerd > ID. Met `*` vallen alle velden van dat formulier erbuiten. Lege regels en spaties tellen niet, en een formulier op meer regels telt samen. Een ongeldige regel wordt niet opgeslagen en geeft een melding; de geldige regels wel.

Een uitgezonderd veld wordt overgeslagen op dezelfde plek als de veldtypes zonder checks: het telt voor geen enkele check mee, ook niet voor dubbele inhoud, en komt niet in `log.txt` of `near-miss.txt`. Met debug aan staat in `debug.txt` alleen dat het is overgeslagen, zonder de waarde. De andere velden van dat formulier en alle andere formulieren blijven volledig gecontroleerd.

Bedoeld voor lange intakeformulieren waar echte namen, straatnamen of klachtomschrijvingen net onder de drempel voor onleesbare tekst kunnen komen.

## Anti-spam: naamvelden en profielvelden

Een **naamveld** is een tekstveld (`text`) met veld-ID `name`, of met naam, voornaam, achternaam of name in het label, hoofdletters maken niet uit. Heeft het veld geen label, dan telt de placeholder als label. Nooit een naamveld: een veld met e-mail, bedrijf(snaam), website, Instagram, LinkedIn of gebruikersnaam in het label, en een veld met een webadres als placeholder (`https://`, `www.…` of een domeinnaam). Op een naamveld gelden drie checks, elk met een eigen schakelaar op het instellingenscherm, standaard aan:

| Instelling | Weigert | Reden in de log |
|---|---|---|
| `reject_name_underscore` | een underscore, zoals `mostbet_zlSl` | `underscore in naamveld` |
| `reject_name_digits` | 6 of meer cijfers achter elkaar, zoals `NAERTREGE2323839NEYRTHYT` | `cijferreeks in naamveld` |
| `reject_name_domain` | een link of domeinnaam, zoals `onhour.ru` | `domeinnaam in naamveld` |

Voor de domeinnaam geldt dezelfde lijst met domeinen als bij de links, plus alles met `www.` of `http(s)://`. Een voorletter met een punt telt niet als domein, zodat `J.de Vries` mag.

Een berichtveld (`textarea`) met niets anders dan één link, zoals `http://47kg.kr/`, wordt geweigerd met reden `bericht is alleen een link` (`reject_textarea_only_url`, standaard aan). Dat gebeurt in `mediamora_has_link_injection()`, vóór de drempel voor het aantal links.

Een **profielveld** is een veld met Website, Instagram of LinkedIn in het label (of de placeholder als het label leeg is), een veld met een webadres als placeholder, of een waarde die met `@` begint. Daar hoort een domein of accountnaam in, dus geen check op onleesbare tekst en geen vangnetregel "link in kort veld". De andere checks blijven gelden, zoals te veel links, BBCode en verkorte links.

De placeholders komen uit `$record->get_form_settings( 'form_fields' )`, de labels uit de velden van het record.

## Anti-spam: postcode controleren

Een **postcodeveld** is een tekst-, telefoon- of nummerveld (`text`, `tel`, `number`) met postcode of post code in het label of het veld-ID (ook `post_code` en `post-code`), hoofdletters maken niet uit. Heeft het veld geen label, dan telt de placeholder als label. Nooit een postcodeveld: een veld met plaats, adres of straat in het label, zoals "Postcode en woonplaats", want daar hoort meer in dan alleen de postcode.

Geldig is een Nederlandse postcode (vier cijfers zonder 0 vooraan, eventueel één spatie, twee letters: `2921 AB`, `2921ab`) of een Belgische (vier cijfers: `1000`). Witruimte eromheen telt niet, en een leeg veld wordt overgeslagen; of het verplicht is, regelt Elementor.

Een ongeldige postcode krijgt via `$ajax_handler->add_error()` de melding "Vul een geldige postcode in, bijvoorbeeld 2921 AB." bij het veld zelf, zodat een echte bezoeker het kan verbeteren, en komt in `log.txt` met reden `ongeldige postcode`. De check draait vóór de veldtypes zonder checks, zodat ook een als nummerveld gebouwd postcodeveld wordt gecontroleerd. Uitgezonderde velden ("Velden per formulier") worden overgeslagen.

Schakelaar: "Postcode controleren (NL/BE)" onder Modus (`check_postcode`, standaard aan).

## Anti-spam: referrer controleren

Het JavaScript van Elementor Pro voegt bij elke inzending van het formulier zelf het veld `referrer` toe, met het adres van de pagina (`location.href`). Dat veld staat niet in de HTML. Het verborgen veld `referer_title` staat er wel in. In de tabel `e_submissions` komen ze terecht in de kolommen `referer` en `referer_title`.

Nagekeken in Elementor Pro 4.4.1:

- `assets/js/elements-handlers.js`, `getFormData()` van het formulierwidget: `formData.append("referrer", location.toString())`. Het is de handler van het widget zelf, dus het geldt ook voor formulieren in popups en loop-items.
- `modules/forms/classes/ajax-handler.php`: de acties `wp_ajax_elementor_pro_forms_send_form` en `wp_ajax_nopriv_elementor_pro_forms_send_form`.
- `modules/forms/classes/form-record.php`: `isset( $_POST['referrer'] ) ? esc_url_raw( wp_unslash( $_POST['referrer'] ) ) : ''` wordt de meta `page_url`, die Save to database in de kolom `referer` opslaat.
- `modules/forms/widgets/form.php`: `referer_title` is een verborgen input in de HTML. Een bot die de HTML uitleest en het formulier rechtstreeks naar `admin-ajax.php` stuurt, zonder JavaScript uit te voeren, stuurt dus wel `referer_title` mee, maar geen `referrer`.

De check weigert een inzending als `$_POST['referrer']` ontbreekt of leeg is (alleen witruimte telt als leeg). Alleen leeg telt. Het domein wordt niet gecontroleerd, dus staging, subdomeinen en WPML-domeinen gaan gewoon door.

- Geldt alleen voor de ajax-actie `elementor_pro_forms_send_form`, de inzending van het klassieke formulier. Hij draait in `mediamora_antispam_validate_form()` op `elementor_pro/forms/validation`, net als de andere checks. Het atomic formulier van Elementor 4 (`elementor_pro_atomic_forms_send_form`) valt er voorlopig bewust buiten.
- Hij draait vóór alle inhoudelijke checks. Een inzending zonder referrer ligt er meteen uit en de velden worden niet meer bekeken. Dat geldt ook voor een formulier met uitgezonderde velden, want het gaat niet om de inhoud van een veld.
- Bij een weigering zet de check via `$ajax_handler->add_error( 'referrer', … )` dezelfde algemene foutmelding als de andere weigeringen. Er komt één regel in `log.txt`, die meetelt in de rapportmail:
  `… | veld: referrer (n.v.t.) | reden: verstuurd zonder JavaScript (geen referrer) | waarde: formulier: Contact (94c5fa7), referer_title: Contact - Voorbeeld`
  Als waarde staan de formuliernaam, het formulier-ID en de meegestuurde `referer_title` erin, geen veldwaardes.
- Acquisitie die iemand met de hand in een echte browser invult, heeft wel een referrer. Die vangt deze check niet; daar zijn de andere checks voor.

Aanleiding: een telling op 4 sites (25 november 2026) gaf 240 inzendingen, waarvan 28 met een lege `referer`. Die 28 waren allemaal botspam (de prijs-bot RobertPhirl/RobertAgimi/Robertanowl, AndrewCer/Andrewneent, gokspam en onzintekst). Ze kwamen door alle inhoudelijke checks en door de honeypot van Elementor heen. De andere 212, waaronder ruim 180 echte aanmeldingen op hyhb.nl en alle tests, hadden een gevulde `referer`.

Schakelaar: "Referrer controleren" onder Modus (`check_referrer`, standaard aan). Per site uit te zetten op het instellingenscherm. Bij een upgrade blijven de bestaande instellingen staan en komt deze er aan bij. Zet hem uit als een site formulieren op een andere manier laat versturen, bijvoorbeeld met eigen JavaScript dat de inzending zelf opbouwt.

## Anti-spam: tijden in de log

`log.txt`, `near-miss.txt` en `debug.txt` krijgen vanaf 1.28.0 de tijd in de tijdzone van de site, met de afwijking van UTC erachter: `2026-11-20 17:43:42 +01:00 | …`. Regels van daarvoor staan in UTC zonder tijdzone en worden ook zo gelezen. In de rapportmail en de vangnet-mail staan alle regels in de tijdzone van de site, ook de oude. Het mailvenster en de bewaartermijn rekenen met de echte tijd van elke regel, dus bij de overstap wordt niets dubbel gemaild of overgeslagen.

## E-mailadressen afschermen

Schermt e-mailadressen op de voorkant af voor spambots, terwijl bezoekers ze gewoon zien, aanklikken en kopiëren. Staat standaard uit en is per site aan te zetten op het tabblad Beveiliging.

### Aanpak en waarom

De module werkt op de uiteindelijke HTML van de pagina, met een output buffer die op `template_redirect` start. Zo valt alles eronder wat Elementor rendert, ook de header en footer van de Theme Builder, popups, widgets, `the_content` en shortcodes, zonder per widget een filter dat bij een volgende Elementor-versie kan verschuiven. De buffer zit binnen die van LiteSpeed Cache, dus LiteSpeed optimaliseert en cachet HTML die al is afgeschermd.

Per plek:

- **mailto-link**: de `href` wordt `mailto:info[at]domein.nl` en het echte adres staat versleuteld in `data-mm-e`. Een klein script zet de echte `href` terug.
- **aria-label, title en alt** met een adres erin, zoals het icoon van een Pictogramvak: zelfde aanpak, met `info [at] domein.nl` in de plaats.
- **zichtbare tekst**: het adres wordt `<span data-mm-t="…">info [at] domein.nl</span>`. Het script vervangt die span door het echte adres als gewone tekst. Het ziet er dus normaal uit, en kopiëren geeft precies het adres. Een los adres zonder link blijft een los adres en wordt niet klikbaar gemaakt.

Het script staat vlak voor `</body>` en draait meteen, dus vóór `DOMContentLoaded` en voordat Elementor zijn links uitleest. Het heeft `data-no-defer`, `data-no-optimize` en `data-no-minify` voor LiteSpeed en `data-cfasync="false"` voor Rocket Loader van Cloudflare: uitgesteld zou de leesbare vorm blijven staan tot de bezoeker iets doet. Er komt alleen een script op pagina's waar iets is afgeschermd.

**Zonder JavaScript** blijft `info [at] domein.nl` staan: leesbaar voor een mens, en een link opent het mailprogramma met `info[at]domein.nl`, dat de bezoeker zelf kan verbeteren. Dat is de afweging. Het alternatief, het echte adres in de HTML laten staan en het alleen met entiteiten of CSS verstoppen, houdt geen bot tegen die de HTML gewoon decodeert, en de trucs van ASE (omgekeerde tekst met `direction: rtl`, een verborgen span) geven bij kopiëren een verkeerd adres en het adres is niet klikbaar. Met JavaScript, dus voor vrijwel elke bezoeker, werkt alles volledig.

De versleuteling is geen beveiliging, alleen genoeg om bots die op een `@` of `mailto:` zoeken niets te laten vinden: per waarde een sleutelbyte, daarna elke byte van de UTF-8-tekst XOR die sleutel, als hex. De sleutel volgt uit de waarde, zodat dezelfde pagina steeds dezelfde HTML geeft.

Adressen die met entiteiten zijn geschreven, zoals `antispambot()` ze maakt (`&#109;a&#114;ianne&#64;…`), worden ook herkend. Bestandsnamen als `logo@2x.png` en losse handles als `@artsenkompas` niet.

### Wat ongemoeid blijft

- Alles vóór `<body>`: meta-tags en het schema van Rank Math (Organization, WebSite, WebPage).
- De inhoud van `script` (dus ook JSON-LD in de body, zoals het FAQ-schema van een accordeon), `style`, `textarea`, `noscript`, `template`, `form`, `select`, `svg`, `iframe`, `object` en `math`, en HTML-commentaar.
- Alle andere attributen dan `href`, `aria-label`, `title` en `alt`, dus ook `value` en `placeholder` van invoervelden en de `data-settings` van Elementor. Een `href` die niet met `mailto:` begint blijft staan.
- Het beheer, de Elementor-editor en het voorbeeld (`elementor-preview`), het WordPress-voorbeeld, de customizer, REST, AJAX, cron, feeds, robots.txt, XML-RPC en WP-CLI. `wp-login.php` komt niet bij `template_redirect`. Uitgaande mail gaat niet door de buffer.
- Antwoorden die geen HTML zijn: een `Content-Type` anders dan `text/html`, of geen `<body>` (sitemaps, JSON).

Per verzoek is de module uit te zetten met het filter `mm_email_afschermen`, bijvoorbeeld voor één pagina:

```php
add_filter( 'mm_email_afschermen', function ( $aan ) {
	return is_page( 'contact' ) ? false : $aan;
} );
```

### ASE en de shortcode

Staat Email Address Obfuscator van ASE (`obfuscate_email_address` in de optie `admin_site_enhancements`) nog aan terwijl de module aan staat, dan staat er in de toolkit-instellingen een melding dat die uit kan. Ze zitten elkaar niet in de weg: de omgekeerde tekst van ASE is voor de module geen adres.

Gaat de instelling in ASE uit, dan zou `[obfuscate email="…"]` als tekst op de pagina komen. Daarom registreert de module die shortcode zelf zolang ASE dat niet doet, met dezelfde attributen (`email`, `subject`, `text`, `display`, `link`, `class`). Het adres komt er gewoon in, als link bij `link="yes"` of `link="mailto"`, en de buffer schermt het daarna af. Anders dan bij ASE is het adres dan wel klikbaar als `link` aan staat.

### Cache

Bij aan- en uitzetten gaat de hele LiteSpeed-cache leeg, via een zelfherstel op `init` met de stand in `mm_toolkit_email_afschermen_stand`, net als bij Afbeeldingstitels verbergen. Zo vangt het ook een define in `wp-config.php`. De element cache van Elementor hoeft niet leeg: de module werkt pas op de uiteindelijke pagina. Een CDN met een eigen paginacache (Cloudflare APO, QUIC.cloud CDN) leegt mee met LiteSpeed of moet apart geleegd worden.

## Formulieren: nieuwsbrief alleen bij vinkje

Elementor Pro voert de koppeling met ActiveCampaign (ook MailBlue, dezelfde API) of MailerLite bij elke inzending uit. Met deze module (`nieuwsbrief_vinkje`, tabblad Koppelingen, standaard uit) gebeurt dat alleen als de bezoeker een vinkveld heeft aangezet. De module is alleen aan te zetten als Elementor Pro actief is.

### Deel A: koppeling alleen bij vinkje

In de editor staat onderaan de sectie ActiveCampaign en de sectie MailerLite van de formulierwidget het tekstveld **Alleen uitvoeren als dit veld is aangevinkt** (`mm_activecampaign_vinkveld` en `mm_mailerlite_vinkveld`, één per koppeling, want een formulier kan beide gebruiken). Daar komt het ID van het vinkveld, zoals het bij het veld onder Geavanceerd > ID staat, bijvoorbeeld `nieuwsbrief`. Leeg betekent altijd uitvoeren, zoals zonder de module.

- Is het vinkveld bij een inzending niet aangevinkt (`raw_value` leeg), dan slaat de module de koppeling voor die inzending stil over. Geen foutmelding voor de bezoeker; de mail, de opgeslagen inzending en de andere acties gaan gewoon door. Met `WP_DEBUG_LOG` aan komt er een regel in de debuglog, zonder gegevens van de bezoeker.
- Staat het veld-ID niet in het formulier, bijvoorbeeld door een tikfout, dan draait de koppeling gewoon en komt er een melding in het beheer met het formulier, de koppeling en het veld-ID (optie `mm_nieuwsbrief_vinkje_meldingen`, geen autoload). De melding verdwijnt zodra dat formulier weer zonder tikfout is ingezonden, of met de link Melding wegklikken.
- Hoe: het filter `elementor_pro/forms/submit_actions` (sinds Elementor Pro 4.0.3), dat vlak voor het uitvoeren de lijst met acties van die inzending geeft. De module haalt `activecampaign` of `mailerlite` uit die lijst. De klassen van Elementor Pro blijven ongemoeid, dus API-sleutel, lijst of groep, tags en veldkoppelingen werken zoals altijd. Eerst was het plan om de acties te vervangen door een eigen subclass, maar `Registrar::register()` weigert een naam die al bestaat en de constructors van de acties hangen ajax- en adminhooks op; het filter is eenvoudiger en vangt hetzelfde af.
- Op Elementor Pro ouder dan 4.0.3 staat het tekstveld niet in de editor en meldt het beheer dat Elementor Pro bijgewerkt moet worden. Deel B werkt daar wel.

### Deel B: lege vinkvelden als Nee

Altijd actief zolang de module aan staat, voor alle Elementor-formulieren van de site. Op `elementor_pro/forms/record/actions_before` krijgt elk acceptatieveld en elke checkbox met precies één optie `value` "Nee" als `raw_value` leeg is; een aangevinkte acceptatie ("on") wordt "Ja". Een aangevinkte checkbox houdt zijn eigen waarde, bij de optie `tekst|Ja` dus "Ja". Alleen `value` verandert, via `Form_Record::update_field()`; `raw_value` blijft zoals hij binnenkwam, en deel A kijkt daarnaar. Checkboxen met meer opties en andere veldtypes blijven ongemoeid.

De mail (`[all-fields]` en `[field id="..."]`), de opgeslagen inzending (Opslaan in database) en de koppelingen lezen `value` na dit filter, dus overal staat Nee of Ja. Gaat een acceptatieveld via de veldkoppeling naar ActiveCampaign of MailerLite, dan komt daar "Ja" in plaats van "on".

De anti-spam draait eerder, op `elementor_pro/forms/validation`, en ziet de velden nog zoals ze binnenkwamen. De Formuliermonitor hangt aan `wp_mail_failed` en merkt er niets van.

Testgevallen in `tests/nieuwsbrief-vinkje.php`.

## Formuliermonitor: hersteld

De monitor hangt aan `wp_mail_failed` en meldt een fout met de kale `mail()` van PHP, niet met `wp_mail()`, maximaal `MM_MONITOR_MAX_PER_DAG` keer per site per dag (standaard 1). Sinds 1.33.0 houdt hij ook bij of het weer werkt.

- **Openstaande storing.** Bij de eerste fout opent `mm_monitor_verwerk_fout()` een storing in de optie `mm_monitor_storing` (geen autoload): `sinds` (Unix-tijd), `fouten` (aantal tot nu toe) en `gemeld` (of er voor deze storing een melding is verstuurd). Volgende fouten tellen bij dezelfde storing, ook over de dagen heen. De dagteller `mm_monitor_teller` is alleen voor de dagcap en werkt zoals altijd.
- **Hersteld.** Op `wp_mail_succeeded` (WordPress 5.9 en hoger) wordt een openstaande storing gesloten. Is daarvoor een melding verstuurd, dan volgt één melding "hersteld" met site, tijdstip van herstel, sinds wanneer, de duur en het aantal fouten. Is er niets gemeld, bijvoorbeeld door de dagcap of omdat `mail()` op de hosting niet werkte, dan gaat de storing stil dicht. De herstelmelding gaat via dezelfde `mail()`-route en telt niet mee voor de dagcap. Gaat het daarna dezelfde dag weer mis, dan geldt de dagcap zoals altijd: na één storingsmelding die dag komt er geen nieuwe, en dan ook geen herstelmelding.
- De storing gaat eerst dicht en pas daarna gaat de herstelmelding de deur uit, alleen als `delete_option()` de storing echt weghaalde. Twee verzoeken die tegelijk een mail versturen geven zo geen twee herstelmeldingen. Mislukt de herstelmelding zelf, dan wordt hij niet opnieuw geprobeerd.
- Mislukt één mail en lukt de volgende meteen, bijvoorbeeld omdat een bezoeker een niet-bestaand adres invulde voor de bevestiging, dan komen er een storing- en een herstelmelding vlak na elkaar. Dat zegt dan dat de mail van de site zelf het gewoon doet.
- Een plugin die `wp_mail()` helemaal vervangt en `wp_mail_failed` of `wp_mail_succeeded` niet afvuurt, ontloopt de monitor, net als voorheen.
- **Laatste geslaagde mail.** Het tijdstip staat in `mm_monitor_laatst_gelukt` (Unix-tijd, geen autoload). Omdat `wp_mail_succeeded` bij elke mail afvuurt, gaat het hooguit één keer per uur naar de database (`MM_MONITOR_GELUKT_INTERVAL`). Zonder openstaande storing kost een geslaagde mail dus hooguit het lezen van twee opties en eens per uur één keer schrijven. De waarde loopt daardoor maximaal een uur achter.

Onderwerpen en koppen:

| Melding | Onderwerp | `X-Mediamora-Status` |
| --- | --- | --- |
| Storing | `[Formuliermonitor] <domein>` | `storing` |
| Hersteld | `[Formuliermonitor] <domein>: hersteld` | `hersteld` |
| Testknop | `[Formuliermonitor] <domein>` | `test` |

`X-Mediamora-Monitor: 1` staat op alle drie; filter daarop in de mailbox, en op `X-Mediamora-Status` om de soorten uit elkaar te halen. De storingsmelding en de testknop zijn verder gelijk gebleven.

**Testen op een echte site.** Zorg dat de Formuliermonitor laadt (Onderhoudsklant aan, Formuliermonitor aan) en dat er vandaag nog geen storingsmelding is verstuurd. Laat `wp_mail()` mislukken en lukken, bijvoorbeeld met WP-CLI:

```
wp eval 'add_action( "phpmailer_init", function ( $m ) { $m->Mailer = "smtp"; $m->Host = "127.0.0.1"; $m->Port = 1; $m->SMTPAutoTLS = false; }, PHP_INT_MAX ); wp_mail( "hetty@mediamora.nl", "Monitortest", "Moet mislukken" );'
wp option get mm_monitor_storing --format=json
wp eval 'wp_mail( "hetty@mediamora.nl", "Monitortest", "Moet lukken" );'
wp option get mm_monitor_storing
```

De eerste regel geeft de storingsmelding en een storing met `gemeld` op `true`. Na de tweede `wp_mail()` is de optie weg en komt de melding "hersteld" binnen. Om de stille sluiting te zien: dezelfde dag nog een keer laten mislukken (geen melding, `gemeld` `false`) en laten lukken (geen herstelmelding). Met een SMTP-plugin zoals FluentSMTP of WP Mail SMTP kan het mislukken ook door het wachtwoord daar tijdelijk fout te zetten. Ruim daarna `mm_monitor_teller` op als je dezelfde dag nog een echte melding wilt kunnen krijgen.

Testgevallen in `tests/formuliermonitor-hersteld.php`.

## Formuliermonitor: maandelijkse formuliertest

Sinds 1.34.0 test de Formuliermonitor één keer per maand de Elementor Pro-formulieren en de mailroute. Alleen met Onderhoudsklant en de Formuliermonitor aan; de code staat in `modules/formuliertest.php` en laadt met de module mee. Uitgangspunt: alleen een melding als er iets mis is, nooit bij een geslaagde test.

**Moment.** Elke site heeft een vast moment in de eerste 72 uur van de maand, afgeleid van het domein (`crc32` van het domein zonder www, modulo 259200 seconden, vanaf middernacht in de tijdzone van de site). Een eenmalig cron-event `mm_formuliertest_maandelijks` staat op dat moment; na de ronde komt het volgende erin. Vangnet: is het moment van deze maand voorbij en de ronde van deze maand niet gedaan (`mm_formuliertest_maand`, autoload), dan gaat de ronde bij het eerstvolgende verzoek alsnog, ook na de eerste drie dagen. Bij de eerste keer (na de update of het aanzetten) telt de ronde van de lopende maand als gedaan, zodat niet alle sites tegelijk beginnen zodra ze 1.34.0 krijgen. Handmatig draaien verschuift het moment niet.

**Welke formulieren.** Formulieren met de actie `email` of `email2` (zonder opgeslagen `submit_actions` geldt de standaard van Elementor Pro, `email`) op gepubliceerde pagina's en berichten, en in Theme Builder-templates en popups die in gebruik zijn: met weergavevoorwaarden (`_elementor_conditions`), of een popup die via de dynamische tag Popup vanuit een gepubliceerde pagina, bericht of template wordt geopend. Templates eerst, dan pagina's, dan berichten. Hetzelfde formulier via een globale widget of via de widget Template op meer pagina's telt één keer. Hooguit 10 per site.

**Hoe.** Zoals `Ajax_Handler::ajax_send_form()` in Elementor Pro (nagekeken in 4.3.3): het formulier opzoeken met `Module::find_element_recursive()` in het document van `post_id`, een globale widget vervangen door zijn template, het widget maken met `elements_manager->create_element_instance()` en `get_settings_for_display()` als instellingen, `switch_to_post()` naar de pagina, dan `new Form_Record()`, `validate()` en `process_fields()`. `$_POST` krijgt tijdens de test `post_id`, `form_id`, `queried_id`, `referrer` (het adres van de pagina) en `referer_title`, zoals het JavaScript van Elementor Pro ze meestuurt; de referrercheck van de anti-spam doet dus mee en weigert niet onterecht. Daarna draait alleen `run()` van de acties `email` en `email2`. Wat in `ajax_send_form()` na `process_fields()` komt, slaat de test over: de filters `elementor_pro/forms/record/actions_before` en `elementor_pro/forms/submit_actions` (daar haakt Formulieren: nieuwsbrief alleen bij vinkje in), de andere acties (Inzendingen opslaan, webhook, redirect, MailerLite, ActiveCampaign, Mailchimp, popup en de rest), `elementor_pro/forms/actions/after_run`, de componenten activity_log en cf7db en `elementor_pro/forms/new_record`. De `Ajax_Handler` wordt gemaakt zonder constructor (`ReflectionClass::newInstanceWithoutConstructor()`), want die hangt zich aan de ajax-acties.

**De mail onderscheppen.** Alleen tijdens de test van één formulier, in drie lagen: het filter `wp_mail` (als laatste) legt de opgebouwde mail vast en haalt daarna alle ontvangers eruit, ook `Cc` en `Bcc` in de koppen; `pre_wp_mail` (als eerste) laat `wp_mail()` meteen `true` geven, zodat `wp_mail_succeeded` en `wp_mail_failed` niet afvuren; `phpmailer_init` haalt de ontvangers uit PHPMailer voor een plugin die `pre_wp_mail` overslaat. Een formulier is geslaagd als elke mail van de mailactie een geldig ontvangstadres, een onderwerp en inhoud heeft. Een plugin die `wp_mail()` helemaal vervangt en geen van deze haken gebruikt, ontloopt het onderscheppen; dat geldt ook voor de Formuliermonitor zelf. Een maillog-plugin die op `wp_mail` haakt, ziet de testmails wel.

**Testgegevens.** Naam Hetty, e-mail `hetty@mediamora.nl`, telefoon 0612345678, postcode 9700 AA, plaats Groningen (een veld met postcode en plaats: allebei), tekstvakken "Automatische maandelijkse test van Mediamora, niet beantwoorden.", overige tekst- en wachtwoordvelden "test", een url-veld `https://mediamora.nl`, keuzevelden de eerste optie met een waarde (een eerste optie zonder waarde, zoals "Kies een onderwerp|", is een placeholder), acceptatie aan, datum vandaag, tijd 12:00, getal het minimum (anders 1), verborgen velden hun standaardwaarde, de honeypot leeg. Het label bepaalt welk tekstveld wat krijgt (e-mail, postcode, plaats, telefoon, naam). Een captcha als veld (reCAPTCHA, reCAPTCHA v3, hCaptcha, Turnstile: elk veldtype met captcha of turnstile in de naam), een verplichte upload of een verplicht veld van een onbekend type: dan wordt het formulier niet getest en staat de reden erbij. Controleert een plugin een captcha zelf in de validatie, zonder veld in het formulier, dan geeft dat een fout bij een sleutel die geen veld is; met captcha, Turnstile, robot of human in de melding telt het formulier ook als niet te testen.

**Niet meetellen.** Zolang de test loopt (`mm_formuliertest_bezig()`) schrijft de anti-spam niets in `log.txt`, `near-miss.txt` of `debug.txt` en plant hij geen rapportmail; de reden van een weigering gaat naar de test. Er draait geen actie die iets opslaat, dus niets in Inzendingen. De Formuliermonitor ziet een mislukte mail niet als storing en een geslaagde niet als herstel.

**Uitkomst per formulier:** `geslaagd`, `geweigerd` (door de anti-spam, met de reden en het veld), `validatiefout` (welk veld en de melding), `mail_niet_op_te_bouwen` (de reden, adressen gemaskeerd) of `niet_te_testen` (de reden). Daarnaast de mailroute en het totaal: `mislukt` zodra iets anders dan geslaagd of niet te testen voorkomt of de mailroute mislukt, `niet_getest` als geen formulier geslaagd is en de mailroute niet getest, anders `in_orde`. Alleen de laatste ronde staat in `mm_formuliertest_laatste` (geen autoload), zonder ingevulde waarden.

**Mailroute.** Per ronde precies één echte mail via `wp_mail()` naar de constante `MM_FORMULIERTEST_ONTVANGER` in `wp-config.php`, met onderwerp `[Formuliertest <domein>] mailroute` en de koppen `X-Mediamora-Monitor: 1` en `X-Mediamora-Status: formuliertest`. Zonder de constante, met een ongeldig adres of met hetzelfde adres als `MM_MONITOR_ONTVANGER` wordt de mail overgeslagen en staat de mailroute op `niet_getest` met de reden. Hij gaat nooit naar `MM_MONITOR_ONTVANGER`.

```php
define( 'MM_FORMULIERTEST_ONTVANGER', 'formuliertest@mediamora.nl' );
```

**Melding.** Alleen als de ronde mislukt: één melding per site per ronde naar `MM_MONITOR_ONTVANGER`, via `mail()` van de Formuliermonitor, onderwerp `[Formuliermonitor] <domein>: formuliertest mislukt`, `X-Mediamora-Status: formuliertest-mislukt`, met per formulier de uitkomst en de mailroute. Telt niet mee voor de dagcap van de storingsmeldingen.

**Handmatig.** Op het tabblad Mediamora staat onder de tabel het overzicht van de laatste ronde met de knop Nu testen (alleen voor `@mediamora.nl`, met nonce), en in de statuskolom van de Formuliermonitor een korte regel. Met WP-CLI:

```
wp mediamora formuliertest
wp mediamora formuliertest --status
```

Het eerste draait een ronde en toont de uitkomst, het tweede alleen de laatste ronde. Het commando bestaat alleen als de Formuliermonitor laadt. Een ronde die al loopt (slot `mm_formuliertest_slot`, vervalt na tien minuten) geeft een foutmelding.

**Testen op mediamora.nl.** Zet `MM_FORMULIERTEST_ONTVANGER` in `wp-config.php` (een tijdelijk eigen adres om de mailroute te zien aankomen), zorg dat Onderhoudsklant en de Formuliermonitor aan staan, en draai `wp mediamora formuliertest` of de knop Nu testen. Verwacht: per formulier `geslaagd`, één mail `[Formuliertest mediamora.nl] mailroute` en geen mail van de formulieren zelf, niets nieuws in Elementor > Inzendingen of in `log.txt` van de anti-spam, en geen melding. Om de melding te zien: zet in één formulier tijdelijk een ongeldig ontvangstadres bij de actie E-mail (bijvoorbeeld `geen-adres`) en draai opnieuw; dan komt er één melding met `mail niet op te bouwen`.

Testgevallen in `tests/formuliertest.php`, met een nagebootste Elementor Pro in `tests/hulp/elementor-pro-nep.php`.

## Anti-spam: samenvatting voor het dashboard

Sinds 1.34.0 staan achter de waarde in elke regel van `log.txt` het formulier en kenmerken van de hele inzending, zonder waarden: `| formulier: Contact (abc1234) | kenmerken: mail=geldig links=0 latijn=ja`. `mail` is `geldig`, `ongeldig` of `geen` (de velden van het type e-mail), `links` het aantal links in de andere velden (zoals `mediamora_antispam_count_links()` ze telt, ook een kaal domein) en `latijn` of er geen enkele niet-Latijnse letter in staat. Uitgezonderde velden, wachtwoorden en verborgen velden tellen niet mee. De regel begint zoals voorheen, dus de rapportmail en de opschoning werken gewoon. Omdat de waarde vrije tekst is, leest `mediamora_antispam_lees_regel()` formulier en kenmerken van achteren.

`mediamora_antispam_samenvatting()` telt over de afgelopen 30 dagen de weigeringen. Regels met hetzelfde tijdstip en formulier zijn één inzending (bijvoorbeeld dubbele inhoud in twee velden). Een weigering lijkt op een mens als alle redenen in `MEDIAMORA_ANTISPAM_MENSELIJKE_REDENEN` staan (underscore, cijferreeks of domeinnaam in een naamveld, onleesbare tekst, herhaalde trefwoorden), er een geldig ogend e-mailadres is, er geen links in staan en alles Latijns schrift is. Nooit menselijk (`MEDIAMORA_ANTISPAM_NOOIT_MENSELIJK`): geen referrer, niet-Latijns schrift, een verkorte URL en een boekingslink; alle andere redenen ook niet. Een ongeldige postcode (`MEDIAMORA_ANTISPAM_POSTCODE_REDEN`) telt apart, want die kon de bezoeker zelf verbeteren. Regels van voor 1.34.0 hebben geen kenmerken; daar telt alleen of de geweigerde waarde geen link en Latijns schrift heeft.

Naar het dashboard gaan alleen tijdstip, formulier en reden. De ingevulde waarden blijven in het log op de site.

Testgevallen in `tests/anti-spam-samenvatting.php`.

## Status voor MainWP

Is MainWP Child actief, dan geeft de toolkit bij de sync van het MainWP-dashboard zijn status mee, onder de sleutel `mediamora_toolkit`. De code staat in `includes/mainwp.php` en niet in de module, zodat het dashboard ook ziet dat een site geen onderhoudsklant is of dat de Formuliermonitor uit staat.

Nagekeken in de broncode van MainWP Child 6.4.2 en MainWP Dashboard 6.4.2:

- **Dashboard**, `class/class-mainwp-sync.php`: bij elke sync gaat de lijst `othersData` mee, gevuld met het filter `mainwp_sync_others_data` (`$othersData`, `$website`) en als JSON verstuurd.
- **Site**, `class/class-mainwp-child-stats.php`, `stats_others_data()`: MainWP Child decodeert `othersData` en geeft die door aan het filter `mainwp_site_sync_others_data` (`$information`, `$othersData`). Wat daar aan `$information` wordt toegevoegd, gaat in het antwoord terug. Het filter draait alleen als het dashboard `othersData` meestuurt en de sync-instelling `othersData` niet uit staat (`syncdata`, optie `mainwp_child_settings_sync_data`). De oude naam `mainwp-site-sync-others-data` is verouderd.
- **Dashboard**, weer `class-mainwp-sync.php`: het antwoord komt binnen bij de actie `mainwp_site_synced` (`$website`, `$information`), alleen na een geslaagde sync. Daarvoor zijn er ook `mainwp_before_save_sync_result` (filter) en `mainwp_site_sync` (actie, ook bij een mislukte sync).

Het filter op de site draait bij elke sync, ook als niemand erom vraagt. De extensies van MainWP zelf (Pagespeed, Client Reports en andere) voegen hun gegevens toch alleen toe als het dashboard er met een eigen sleutel in `othersData` om vraagt. De toolkit doet dat ook: **de dashboardplugin moet in `mainwp_sync_others_data` de sleutel `mediamora_toolkit` met een ware waarde zetten**, anders gaat er niets mee. Bijvoorbeeld:

```php
add_filter( 'mainwp_sync_others_data', function ( $data, $website ) {
	$data['mediamora_toolkit'] = 1;
	return $data;
}, 10, 2 );

add_action( 'mainwp_site_synced', function ( $website, $information ) {
	if ( isset( $information['mediamora_toolkit'] ) && is_array( $information['mediamora_toolkit'] ) ) {
		// Opslaan bij $website->id.
	}
}, 10, 2 );
```

De structuur, schema 2:

```
mediamora_toolkit => array(
	'schema'           => 2,                 // int, omhoog als de structuur verandert
	'toolkit_versie'   => '1.34.0',          // string, de Version uit de pluginkop
	'onderhoudsklant'  => true,              // bool
	'formuliermonitor' => array(
		'aan'                    => true,    // bool: de module laadt
		'storing'                => null,    // of array( 'sinds' => ISO 8601, 'fouten' => int, 'gemeld' => bool )
		'laatste_fout'           => '2026-11-27T14:05:00+01:00',  // ISO 8601 of null
		'laatste_geslaagde_mail' => '2026-11-27T15:00:12+01:00',  // ISO 8601 of null, loopt hooguit een uur achter
		'log'                    => array(   // de laatste 5 regels uit mm_monitor_log, nieuwste eerst
			array(
				'tijd'      => '2026-11-27T14:05:00+01:00',  // ISO 8601 of null
				'code'      => 'wp_mail_failed',
				'melding'   => 'SMTP Error: The following recipients failed: ***@voorbeeld.nl',
				'ontvanger' => '@voorbeeld.nl',              // alleen domeinen, of leeg
			),
		),
	),
	'anti_spam'        => array(             // of null, zie hieronder
		'periode'    => array(
			'van'   => '2026-10-29T12:00:00+01:00',  // ISO 8601
			'tot'   => '2026-11-28T12:00:00+01:00',  // ISO 8601, het moment van de sync
			'dagen' => 30,
		),
		'totaal'     => 14,                  // int, geweigerde inzendingen
		'menselijk'  => 2,                   // int, waarvan die op een mens lijken
		'postcode'   => 1,                   // int, met een ongeldige postcode (apart, kan ook menselijk zijn)
		'menselijke' => array(               // hooguit 10, nieuwste eerst
			array(
				'tijd'      => '2026-11-27T09:14:02+01:00',  // ISO 8601
				'formulier' => 'Contact (abc1234)',          // naam en ID, of null voor regels van voor 1.34.0
				'reden'     => 'onleesbare tekst',           // redenen met komma's
			),
		),
	),
	'testinzending'    => array(             // of null, zie hieronder
		'laatste_ronde'  => '2026-11-02T14:23:51+01:00',  // ISO 8601, of null als er nog geen ronde was
		'handmatig'      => false,           // bool: met de knop of WP-CLI gestart
		'volgende_ronde' => '2026-12-02T15:10:07+01:00',  // ISO 8601, of null
		'totaal'         => 'in_orde',       // in_orde, mislukt of niet_getest
		'mailroute'      => array(           // of null als er nog geen ronde was
			'uitkomst' => 'geslaagd',        // geslaagd, mislukt of niet_getest
			'melding'  => null,              // string bij mislukt of niet_getest, adressen gemaskeerd
		),
		'formulieren'    => array(           // hooguit 10
			array(
				'pagina'    => 'Contact',        // titel van de pagina, of "Titel (soort)" bij een template of popup
				'formulier' => 'Contactformulier',  // form_name, of het element-ID
				'uitkomst'  => 'geslaagd',       // geslaagd, geweigerd, validatiefout, mail_niet_op_te_bouwen of niet_te_testen
				'reden'     => null,             // string, of null bij geslaagd
			),
		),
	),
)
```

- Alle tijden zijn ISO 8601 met de tijdzone van de site (`DATE_ATOM`).
- Staat de Formuliermonitor niet aan (uit, Onderhoudsklant uit of de losse plugin nog op de site), dan is `aan` `false`, zijn `storing`, `laatste_fout` en `laatste_geslaagde_mail` `null` en is `log` leeg.
- Geen persoonsgegevens, geen onderwerpen, geen volledige adressen en geen instellingen. De logregels gaan nog een keer door `mm_monitor_maskeer_adressen()`, want regels van voor 1.16.0 kunnen nog adressen bevatten, en alleen `tijd`, `code`, `melding` en `ontvanger` gaan mee.
- `testinzending` en `anti_spam` horen bij het onderhoudspakket: `null` als de Formuliermonitor niet laadt. `anti_spam` is ook `null` als de module Anti-spam niet laadt. Zie "Formuliermonitor: maandelijkse formuliertest" en "Anti-spam: samenvatting voor het dashboard".
- Geen ingevulde waarden: de formuliertest bewaart ze nergens, en uit het anti-spamlog gaan alleen tijdstip, formulier en reden mee.
- Schema 2 (1.34.0) vult `anti_spam` en `testinzending`. De sleutels van schema 1 zijn gelijk gebleven, zodat een dashboard dat alleen schema 1 kent (mediamora-mainwp 0.1.1) blijft werken. Nieuwe sleutels mogen erbij komen zonder dat `schema` omhoog gaat; `schema` gaat omhoog als bestaande sleutels anders worden of verdwijnen, of als een sleutel een andere vorm krijgt.

## Deactiveren en verwijderen

Deactiveren haalt alleen de cron-events (ook die van Verweesde meta opruimen, de Instagram-feed, Tijdelijke accounts, Afsluiten op einddatum, de formuliertest en de dagelijkse opschoning van de anti-spamlogs) en de blokken van de toolkit uit `.htaccess` weg, en leegt de element cache van Elementor en de LiteSpeed-cache als Afbeeldingstitels verbergen aan stond, en de LiteSpeed-cache als E-mailadressen afschermen aan stond. Instellingen en logs blijven staan.

Een module uitvinken ruimt alleen op wat zonder de module blijft doorlopen: de cron-events, bij Preview-link de sleutel `mm_preview_key` (alle links vervallen) en bij Afbeeldingstitels verbergen en E-mailadressen afschermen de caches. Gedeelde gegevens blijven staan zolang de losse versie van die module nog op de site staat, actief of niet.

Verwijderen via Plugins ruimt alles op: de opties en transients van de toolkit en de modules (ook `mm_toolkit_onderhoudsklant`), de cron-events, de alt-tekstkenmerken (`_mm_alt_*`, de alt-teksten zelf blijven staan), de productmeta van Herroepingsrecht (`_mm_withdrawal_type` en het oude vinkje `_mm_withdrawal_waiver_applies`; de teksten `_mm_withdrawal_waiver` en `_mm_service_consent` op bestellingen blijven altijd staan, dat is het bewijs van de instemming), de opties van Verweesde meta opruimen, de opties en de fotomap `uploads/mediamora-instagram` van de Instagram-feed, de mailinstelling van Tijdelijke accounts, de stand van Afbeeldingstitels verbergen en van E-mailadressen afschermen, de gegevens van Persoon in schema (`mm_schema_persoon`, `mm_schema_persoon_bezet`), de einddatum en voortgang van Afsluiten op einddatum (`mm_einddatum`, `mm_einddatum_stand`), de teller, het log, de openstaande storing en de laatste geslaagde mail van de Formuliermonitor (`mm_monitor_teller`, `mm_monitor_log`, `mm_monitor_storing`, `mm_monitor_laatst_gelukt`), de laatste ronde en de maand van de formuliertest (`mm_formuliertest_laatste`, `mm_formuliertest_maand`, `mm_formuliertest_slot`; altijd, want de losse Formuliermonitor kent die niet), de meldingen van Formulieren: nieuwsbrief alleen bij vinkje (`mm_nieuwsbrief_vinkje_meldingen`; de veld-ID's staan in de formulieren zelf en blijven daar staan), de `.htaccess`-blokken en de logmap van de anti-spam met de inzendingen erin. Kan de logmap niet worden weggehaald, dan blijft de optie `mediamora_antispam_log_dir` staan, zodat te vinden is waar hij staat. Staat de losse versie van een module nog op de site, als plugin of mu-plugin en actief of niet, dan blijft alles van die module staan, want de losse versie gebruikt dezelfde gegevens. Dat werkt ook met `wp plugin uninstall --deactivate`, waarbij de toolkit in hetzelfde verzoek nog geladen is.

De user meta `mm_verloopt_op` en `mm_overdragen_aan` blijven altijd staan. Worden ze weggehaald en wordt de toolkit daarna opnieuw geïnstalleerd, bijvoorbeeld om een zip te vervangen, dan worden tijdelijke accounts stil vaste accounts.

## Wijzigingen

### 1.34.0

- Formuliermonitor: maandelijkse formuliertest. Op een vast moment per site in de eerste 72 uur van de maand gaan de Elementor Pro-formulieren met een mailactie (hooguit 10) door de normale verwerking van Elementor Pro, met de anti-spam; daarna draaien alleen `email` en `email2`, en hun mail wordt opgebouwd en onderschept. Eén echte mail via `wp_mail()` naar `MM_FORMULIERTEST_ONTVANGER` voor de mailroute. Alleen bij een fout één melding naar `MM_MONITOR_ONTVANGER` met `X-Mediamora-Status: formuliertest-mislukt`. Knop Nu testen op het tabblad Mediamora en `wp mediamora formuliertest`. Zie "Formuliermonitor: maandelijkse formuliertest".
- Anti-spam: formulier en kenmerken (zonder waarden) achter elke regel in `log.txt`, en een samenvatting van de weigeringen van de afgelopen 30 dagen met de weigeringen die op een mens lijken en de postcodeweigeringen apart. Tijdens de formuliertest logt de anti-spam niets. Zie "Anti-spam: samenvatting voor het dashboard".
- Status voor MainWP: schema 2, met `testinzending` en `anti_spam` gevuld. De velden van schema 1 zijn gelijk gebleven.
- Deactiveren, de Formuliermonitor uitzetten en `uninstall.php` halen het event `mm_formuliertest_maandelijks` weg; `uninstall.php` ruimt ook de opties van de formuliertest op.
- PHPStan kent WP-CLI via `php-stubs/wp-cli-stubs` (komt mee met `arts/elementor-stubs`).
- Testgevallen in `tests/formuliertest.php` en `tests/anti-spam-samenvatting.php`. `tests/anti-spam-naamvelden.php` en `tests/formuliermonitor-hersteld.php` zijn bijgewerkt voor de nieuwe logregel en schema 2.

### 1.33.0

- Formuliermonitor: melding "hersteld". Een fout opent een storing (`mm_monitor_storing`, geen autoload). Lukt daarna een mail via `wp_mail()` (`wp_mail_succeeded`), dan gaat de storing dicht en komt er één melding `[Formuliermonitor] <domein>: hersteld` met sinds wanneer, de duur en het aantal fouten, alleen als er voor die storing een melding was verstuurd. Telt niet mee voor de dagcap. Zie "Formuliermonitor: hersteld".
- Formuliermonitor: nieuwe kop `X-Mediamora-Status` met `storing`, `hersteld` of `test`. `X-Mediamora-Monitor: 1` blijft op alle meldingen staan.
- Formuliermonitor: het tijdstip van de laatste geslaagde mail staat in `mm_monitor_laatst_gelukt`, hooguit één keer per uur weggeschreven.
- Status voor MainWP: met MainWP Child actief geeft de toolkit de sleutel `mediamora_toolkit` mee met de sync, als het dashboard daar in `othersData` om vraagt (filter `mainwp_site_sync_others_data`). Schema 1 met de versie, Onderhoudsklant en de Formuliermonitor; ruimte voor anti-spam en de testinzending. Zie "Status voor MainWP".
- `mm_monitor_opruimen()` en `uninstall.php` ruimen de twee nieuwe opties ook op.
- Testgevallen in `tests/formuliermonitor-hersteld.php`.

### 1.32.0

- Anti-spam: referrercheck. Een inzending van het klassieke Elementor Pro-formulier (ajax-actie `elementor_pro_forms_send_form`) zonder of met een lege `referrer` wordt geweigerd met de algemene foutmelding. In `log.txt` komt de reden `verstuurd zonder JavaScript (geen referrer)`. Alleen leeg telt, niet het domein. De check draait vóór de inhoudelijke checks. Eigen schakelaar "Referrer controleren" (`check_referrer`, standaard aan). Aanleiding: 28 van 240 inzendingen op 4 sites hadden geen referrer, en die waren allemaal botspam die door de andere checks en de honeypot kwam. Zie "Anti-spam: referrer controleren".
- Testgevallen in `tests/anti-spam-referrer.php`.

### 1.31.0

- Anti-spam: postcodecheck. Een postcodeveld (postcode of post code in het label of het veld-ID) moet een Nederlandse (`2921 AB`, `2921ab`) of Belgische (vier cijfers) postcode bevatten. Anders krijgt de bezoeker bij het veld de melding "Vul een geldige postcode in, bijvoorbeeld 2921 AB." en komt de inzending in het log met reden `ongeldige postcode`. Een leeg veld en uitgezonderde velden worden overgeslagen, net als een veld met ook plaats, adres of straat in het label. Eigen schakelaar "Postcode controleren (NL/BE)" (`check_postcode`, standaard aan). Aanleiding: een bot die honderden keren met een zescijferige postcode door de honeypot en de andere checks kwam. Zie "Anti-spam: postcode controleren".
- Testgevallen in `tests/anti-spam-postcode.php`.

### 1.30.0

- Nieuwe module Formulieren: nieuwsbrief alleen bij vinkje (`nieuwsbrief_vinkje`, tabblad Koppelingen, standaard uit, alleen aan te zetten met Elementor Pro). De koppelingen met ActiveCampaign (ook MailBlue) en MailerLite krijgen in de editor de instelling "Alleen uitvoeren als dit veld is aangevinkt" en worden overgeslagen als dat veld niet is aangevinkt. Een veld-ID dat niet in het formulier staat geeft een melding in het beheer en laat de koppeling gewoon draaien. Daarnaast tonen lege acceptatievelden en checkboxen met één optie in de mail en de inzendingen "Nee", en een aangevinkte acceptatie "Ja". Werkt via het filter `elementor_pro/forms/submit_actions`, dus vanaf Elementor Pro 4.0.3. Zie "Formulieren: nieuwsbrief alleen bij vinkje".
- `vereist` in de modulelijst kent nu ook `elementor_pro`. Het instellingenscherm noemt de juiste plugin als die niet actief is.
- Testgevallen in `tests/nieuwsbrief-vinkje.php`.

### 1.29.0

- Nieuwe module E-mailadressen afschermen (`email_afschermen`, tabblad Beveiliging, standaard uit). Schermt mailto-links, zichtbare adressen en adressen in `aria-label`, `title` en `alt` af in de uiteindelijke HTML van de voorkant, met een klein script dat ze voor bezoekers terugzet. Zonder JavaScript staat er `info [at] domein.nl`. Scripts (ook JSON-LD), formulieren, de kop van de pagina, het beheer, de Elementor-editor, REST, AJAX, feeds en uitgaande mail blijven ongemoeid. Vervangt Email Address Obfuscator van ASE; de shortcode `[obfuscate]` blijft werken. Staat die in ASE nog aan, dan meldt het instellingenscherm dat hij uit kan. Bij aan- of uitzetten wordt de LiteSpeed-cache geleegd. Zie "E-mailadressen afschermen".
- Testgevallen in `tests/email-afschermen.php`.

### 1.28.0

- Anti-spam: drie nieuwe checks op naamvelden, elk met een eigen schakelaar (standaard aan) en een eigen reden in de log: een underscore (`underscore in naamveld`), 6 of meer cijfers achter elkaar (`cijferreeks in naamveld`) en een link of domeinnaam (`domeinnaam in naamveld`). Een domein in een naamveld gaf eerst alleen een vangnetregel "link in kort veld, doorgelaten". Zie "Anti-spam: naamvelden en profielvelden" voor welke velden als naamveld tellen.
- Anti-spam: een berichtveld met niets anders dan één link wordt geweigerd (`bericht is alleen een link`, eigen schakelaar, standaard aan). Eerst gold voor berichtvelden alleen het maximum aantal links.
- Anti-spam: velden met Website, Instagram of LinkedIn in het label, velden met een webadres als placeholder en waarden die met @ beginnen, vallen niet meer onder de check op onleesbare tekst en geven geen vangnetregel "link in kort veld" meer.
- Anti-spam: `log.txt` en `near-miss.txt` bewaren 200 tekens van de waarde, was 50 en 80. Zo is te zien waarop een check aansloeg. De bewaartermijn blijft gelijk.
- Anti-spam: de logtijden staan in de tijdzone van de site met de afwijking van UTC erachter (`2026-11-20 17:43:42 +01:00`), net als de mails. Eerst stond het log in UTC zonder tijdzone, waardoor regels een uur verschoven leken. Bij de overstap: regels van vóór de update blijven in het bestand in UTC staan tot ze na de bewaartermijn verdwijnen, en worden ook als UTC gelezen; in de mails staan ze omgerekend naar de tijd van de site. Het mailvenster rekent met de echte tijd van elke regel, dus de eerste mail na de update mailt niets dubbel en slaat niets over. Zie "Anti-spam: tijden in de log".
- Testgevallen in `tests/anti-spam-naamvelden.php`; de andere anti-spamtests bootsen nu `wp_date()` na.

### 1.27.0

- Anti-spam: velden per formulier uitzonderen van alle checks. Nieuw tekstveld "Velden per formulier" op het instellingenscherm, één regel per formulier (`formulier-ID: veld-ID, veld-ID`, of `formulier-ID: *`). Uitgezonderde velden tellen voor geen enkele check mee en komen niet in de logbestanden; andere velden en formulieren blijven gecontroleerd. Ongeldige regels worden niet opgeslagen en gemeld. Zie "Anti-spam: velden uitzonderen".
- Testgevallen in `tests/anti-spam-uitzonderingen.php`.

### 1.26.1

- Afsluiten op einddatum: de eigen back-upmap uit `wpvivid_local_setting['path']` telt nu met het hele relatieve pad in plaats van alleen de basisnaam. Bij een genest pad als `a/b` ging tot nu toe `wp-content/b` weg, en die map kan van iets anders zijn. Een pad met `..`, een absoluut pad, `wp-content` zelf en een pad dat begint met een map van WordPress (`uploads`, `plugins`, `themes`, `mu-plugins`, `languages`, `upgrade`, `cache`) worden geweigerd, en `realpath()` moet binnen `wp-content` uitkomen. Zie "Afsluiten op einddatum".
- Testgevallen in `tests/einddatum.php` voor `a/b`, `../x`, `uploads/x` en een symbolische link naar `uploads`.

### 1.26.0

- Afsluiten op einddatum verwijdert nu ook de lokale back-ups van WPvivid (`wp-content/wpvividbackups` of de eigen map uit `wpvivid_local_setting`), met aantal bestanden en omvang in de mail en de proefdraai. Daar stond een kopie van de S3-instellingen in.
- Afsluiten op einddatum kijkt vóór het zelf verwijderen of de pluginmap en de mappen van de toolkit schrijfbaar zijn; zo niet, dan blijft de toolkit aan en zegt de mail dat hij zelf weg moet. Mislukt het verwijderen toch, dan volgt een aparte mail.
- Testgevallen in `tests/einddatum.php` uitgebreid.

### 1.25.0

- Nieuwe module Afsluiten op einddatum (`einddatum`) op het tabblad Mediamora, standaard uit. Op de ingestelde einddatum wist de site de remote opslag, de back-upschema's en de Pro-koppeling in WPvivid, verwijdert WPvivid Plugins Pro, WPvivid Backup Plugin, MainWP Child Reports en MainWP Child, mailt het resultaat en verwijdert zichzelf. Met proefdraai en bevestiging bij een datum van vandaag of eerder. Zie "Afsluiten op einddatum".
- Het meldingsadres `MM_MONITOR_ONTVANGER` staat nu in `includes/ontvanger.php` in plaats van in de module Formuliermonitor, zodat het ook bestaat als die module niet laadt. Overschrijven in `wp-config.php` werkt zoals eerst.
- Testgevallen in `tests/einddatum.php`.

### 1.24.0

- Persoon in schema: meerdere personen in plaats van één, elk met een rol (founder of employee). De eerste houdt `@id` `#persoon`, de volgende krijgen `#persoon-<naam>`. Op de Organization worden `founder` en `employee` een lijst bij meer personen.
- Persoon in schema: per persoon een koppeling met een WordPress-gebruiker. Op berichten van die schrijver verdwijnt de losse auteur-Person van Rank Math en wijst de `author` naar de persoon, zodat iedereen maar één keer in het schema staat. Ook op het auteursarchief.
- Persoon in schema: nieuwe velden voor titels vóór en na de naam (`honorificPrefix`, `honorificSuffix`), vakgebieden (`knowsAbout`), registraties (`hasCredential`) en talen (`knowsLanguage`); opleidingen en lidmaatschappen zijn herhaalbaar.
- De gegevens van 1.20 t/m 1.23 worden automatisch omgezet; de uitvoer blijft gelijk zolang er niets nieuws is ingevuld. Zie "Persoon in schema".
- Testgevallen in `tests/schema-persoon.php` uitgebreid.

### 1.23.0

- Nieuwe module Lettertype voorladen (`font_preload`) op het tabblad Snelheid, standaard uit, ook na de update op bestaande sites. Zet één preload in de head voor het woff2-bestand (latin-subset) van het tekstlettertype uit de Elementor-kit, als Elementor de Google Fonts lokaal heeft staan. Voorkomt de layout shift die ontstaat als het lettertype met `font-display: swap` pas na de eerste weergave binnenkomt. Optioneel ook het kopletterype. Zie "Lettertype voorladen".
- Testgevallen in `tests/font-preload.php` en `tests/font-preload-toolkit.php`.

### 1.22.0

- AI-bots: telt ook de bezoekers die via een AI-antwoord binnenkomen (verwijzer of `utm_source` van ChatGPT, Perplexity, Gemini, Copilot, Claude en andere), per bron en per landingspagina, bovenaan het AI-bots-scherm. Telt in de browser via `admin-ajax.php`, zodat gecachete pagina's meetellen; zonder cookies en zonder IP-adres. Zie "AI-bots: bezoekers vanuit AI-antwoorden". Testgevallen in `tests/ai-bots-bezoek.php`.
- AI-bots: het opschonen na zes maanden kijkt naar de maand aan het eind van de optienaam, zodat ook de bezoekerslogs na zes maanden weggaan.

### 1.21.0

- Anti-spam: de rapportmail en de vangnet-mail bevatten alle regels sinds de vorige mail (`mediamora_antispam_last_report` en `mediamora_antispam_last_nearmiss_alert`), niet meer alleen die van de laatste periode voor het verzenden. Een mail wordt pas ingepland bij een nieuwe logregel; zat er meer dan de periode tussen twee mails, dan kwamen de regels daartussen wel in het log maar nooit in een mail. De periode is nu alleen de minimale tijd tussen twee mails. Bij de allereerste mail blijft het de afgelopen periode, en regels van voor de bewaartermijn komen er nooit in. Een regel uit precies de seconde van het verzenden komt in de volgende mail, zodat geen regel dubbel of niet gemaild wordt.
- Anti-spam: wordt de rapportmail of het vangnet weer aangezet, dan begint die bij dat moment en haalt de eerste mail niets in van de tijd dat hij uit stond.
- Anti-spam: een logregel binnen de periode na een mail plant de volgende mail in op het eind van die periode, in plaats van niets te doen. Eerst werd zo'n regel pas gemaild als er na afloop van de periode een nieuwe weigering kwam, en kwam die er niet, dan nooit. Draait het event toch te vroeg (bijvoorbeeld na uit- en weer aanzetten), dan plant het zich opnieuw in op het eind van de periode.
- Anti-spam: het tijdstip van de vorige mail wordt pas opgeslagen als `wp_mail()` lukt. Eerst gebeurde dat vóór het versturen, dus bij een mislukte mail (bijvoorbeeld een SMTP-fout) gingen de regels erin verloren. Nu blijft het vorige tijdstip staan en komt er een nieuwe poging over een periode, met dezelfde regels plus wat er intussen bij kwam. Staat de mail uit of is er niets te melden, dan wordt het tijdstip zoals eerst wel bijgewerkt.
- Testgevallen in `tests/anti-spam-rapport.php`.

### 1.20.0

- Nieuwe module Persoon in schema (`schema_persoon`) op het tabblad Vindbaarheid, standaard uit. Zet de therapeut of coach van een eenmanspraktijk als Person in het schema van Rank Math (`@id` `#persoon`), met `worksFor` naar de Organization en `founder` of `employee` op de Organization. Velden voor naam, functie, omschrijving, pagina, opleiding (`alumniOf`), lidmaatschap (`memberOf`), profielen (`sameAs`), foto en een omschrijving van de praktijk. Doet niets zonder Rank Math, zonder Organization, als de site in Rank Math een persoon is, of als er al een persoon in het schema staat. Vervangt losse code in `functions.php`. Zie "Persoon in schema".
- Testgevallen in `tests/schema-persoon.php`.

### 1.19.0

- Nieuwe module Afbeeldingstitels verbergen (`afbeeldingstitels`) op het tabblad Vindbaarheid, standaard aan. Haalt het title-attribuut weg dat Elementor met een eigen uitsnede op de `<img>` zet, zodat bezoekers bij hover geen bestandsnaam meer zien. Alleen de title gaat eraf, alt en de rest blijven staan. Werkt voor alle widgets die via `Group_Control_Image_Size::get_attachment_image_html()` lopen. Zie "Afbeeldingstitels verbergen".
- Bij aan- en uitzetten van die module, ook via de constante, deactiveren en deze update, worden `_elementor_element_cache` en de LiteSpeed-cache geleegd. Op elke site met de module aan gebeurt dat dus één keer na de update naar 1.19.0.
- Testgevallen in `tests/afbeeldingstitels.php` en `tests/afbeeldingstitels-toolkit.php`.

### 1.18.0

- Nieuwe module Tijdelijke accounts op het tabblad Mediamora, standaard aan en niet onder Onderhoudsklant. Accounts met een verloopdatum (`mm_verloopt_op`) worden overal geweigerd en dagelijks verwijderd, met overdracht van de content aan `mm_overdragen_aan`. Met een mail aan Mediamora, velden op het profiel en bij Nieuwe gebruiker en een overzicht op het tabblad. Vervangt de mu-plugin `mediamora-tijdelijke-accounts.php`, met dezelfde meta en cronhook; zolang dat bestand er staat laadt de module niet. Zie "Tijdelijke accounts".
- Blokkeert anders dan de mu-plugin ook XML-RPC met een applicatiewachtwoord.
- `mm_toolkit_mediamora_adres()` beoordeelt een los adres, voor plekken waar niemand is ingelogd zoals de cron.

### 1.17.0

- Het instellingenscherm is verdeeld in tabbladen per functie: Snelheid, Beveiliging, Vindbaarheid, Webshop, Koppelingen, Bouw en Mediamora. Het actieve tabblad staat in de URL (`?tab=`). Opslaan verandert alleen de modules van het tabblad dat open staat. Optienamen, opgeslagen waarden en constanten zijn ongewijzigd. Zie "Tabbladen en Onderhoudsklant".
- Afbeeldingen: ruimte en voorrang staat onder Snelheid, Elementor: oude versies tegenhouden onder Beveiliging.
- Nieuw tabblad Mediamora met de Formuliermonitor en Verweesde meta opruimen, alleen zichtbaar en op te slaan voor gebruikers met een e-mailadres op `@mediamora.nl` (filter `mm_toolkit_mediamora_domein`). Nu tellen en Testmelding sturen zijn ook alleen voor hen.
- Nieuwe schakelaar Onderhoudsklant (`mm_toolkit_onderhoudsklant`) bovenaan het tabblad Mediamora. Uit zet de modules met de nieuwe markering `onderhoud` stil, ongeacht hun eigen schakelaar of constante. Bestaande sites krijgen hem bij de update alleen aan als de Formuliermonitor uitdrukkelijk aan stond (opgeslagen keuze of constante, geen standaardwaarde), anders uit; nieuwe sites uit. Verweesde meta opruimen telt daarbij niet mee en stopt dus op sites die uitkomen op uit; de opgeslagen keuze blijft bewaard.
- Modules hebben twee nieuwe sleutels in `mm_toolkit_modules()`: `tabblad` en `onderhoud`. `mm_toolkit_status()` geeft per module ook `pakket` (false als de module door Onderhoudsklant uit staat).
- `uninstall.php` ruimt `mm_toolkit_onderhoudsklant` op.

### 1.16.0

Fixes uit de tweede codereview.

- Anti-spam: verkorte links en boekingslinks tellen alleen nog als los domein. E-mailadressen gaan er eerst uit, en het domein mag geen letter, cijfer of streepje ervoor of erna hebben. Zo raakt `cal.com` niet meer `medical.com` en `u.to` niet meer "van u.Tot ziens". `x.co` is uit de lijst: dat raakte `netflix.com`, `dropbox.com` en `gmx.com`. Testgevallen in `tests/anti-spam-links.php` (`php tests/anti-spam-links.php`); de map `tests` zit via `.gitattributes` niet in de releasezip.
- Anti-spam: een eigen dagelijks cron-event `mediamora_antispam_prune_logs` schoont `log.txt`, `near-miss.txt` en `debug.txt` op de bewaartermijn op, los van de mails. Eerst gebeurde dat alleen bij het versturen van een rapportmail of het schrijven van `debug.txt`. Uitzetten, deactiveren en verwijderen halen het event weg. De optie `mediamora_antispam_last_debug_prune` wordt niet meer gebruikt.
- Anti-spam: velden van het type `password` worden niet beoordeeld en niet gelogd, ook niet in `debug.txt`.
- QUIC.cloud: de REST-uitzondering voor `/litespeed/` geldt alleen nog voor wie niet is ingelogd, en een fout `rest_cookie_invalid_nonce` blijft altijd staan. Eerst kon een andere site via de browser van een ingelogde beheerder de routes van LiteSpeed aanroepen.
- `.htaccess`: een blok van de toolkit dat precies één keer, ongewijzigd en boven `# BEGIN WordPress` staat, blijft staan. Eerst duwden het preview- en het AI-bots-blok elkaar bij elke beheerpagina van de eerste plek, en schoof het blok na elke schrijfactie van LiteSpeed Cache weer omhoog.
- Verwijderen: bij `wp plugin uninstall --deactivate` werd niets opgeruimd, omdat de controle op een losse versie de eigen functies van de toolkit vond. `uninstall.php` kijkt nu alleen naar de bestanden van de losse versies (`mm_toolkit_los_bestand_aanwezig()` in `includes/modules.php`), en `MM_TOOLKIT_MAP` wordt alleen gedefinieerd als hij er nog niet is.
- Module uitvinken: `mm_preview_key` en de cron-events van AI-bots en anti-spam blijven staan als de losse versie van die module nog op de site staat.
- Formuliermonitor: e-mailadressen in de melding van de server worden gemaskeerd (`***@voorbeeld.nl`) voordat ze in het log en de mail komen. PHPMailer zet die erin bij "recipients failed". Oudere logregels worden bij de volgende fout ook gemaskeerd.
- Updater: lukt het hernoemen van de map van de update niet, dan stopt de update met een foutmelding en blijft de huidige versie staan. Eerst werd de update met de mapnaam van GitHub geïnstalleerd, als tweede plugin.
- Instagram-feed: staat de module uit, dan is `[mm_instagram]` een lege shortcode, zoals hierboven al beschreven stond; eerst stond de kale tekst op de pagina. De LiteSpeed-tag `mm_instagram` komt er ook op pagina's met een lege feed, zodat die gepurged worden als de eerste posts binnenkomen. Waarden uit de JSON van Behold worden op type gecontroleerd en het antwoord wordt hooguit 2 MB ingelezen.
- Herroepingsrecht: een product met verzending krijgt zonder keuze op het product of de categorie geen vinkje meer; de standaardsoort geldt alleen nog voor producten zonder verzending. Zie "Herroepingsrecht: welke soort per product". Verder `instanceof WC_Email` in de mailhooks, een terugval `#a00` voor `var(--wc-red)`, en het omzetten van de instellingen van 1.6.0 alleen als WooCommerce actief is.
- Hero-preload: op losse pagina's en berichten eerst het single-template van de Theme Builder, en pas zonder template de eigen Elementor-data, zoals Afbeeldingen: ruimte en voorrang al deed. De url in het style-blok van een slideshow gaat door `esc_url_raw()` in plaats van `esc_url()`, zodat een `&` niet als `&#038;` in de CSS komt.
- Afbeeldingen: ruimte en voorrang zet naast `skip-lazy` ook `data-no-lazy="1"`.
- Preview-link: `MM_PREVIEW_KEY` uit `wp-config.php` wordt als string gebruikt, zodat `hash_equals()` geen fout geeft op een getal.
- AI-bots: het cron-event `mm_aibots_opschonen` gaat ook weg als de module via een constante of een update uitgaat (zelfherstel op beheerpagina's), behalve als de losse versie er nog staat.
- HPOS-verenigbaarheid wordt altijd verklaard vanuit het hoofdbestand, niet alleen als de herroepingsmodule laadt.
- `mm_toolkit_modules` wordt met autoload opgeslagen. Bestaande sites worden op de eerste beheerpagina omgezet (WordPress 6.4 en nieuwer).

### 1.15.2

- Anti-spam: een ongeldig of leeg e-mailadres voor meldingen wordt niet meer opgeslagen. Eerst maakte `sanitize_email()` daar een lege string van, die stil werd bewaard, waarna de rapportmail en de vangnet-mail nergens meer aankwamen. Nu blijft het vorige adres staan en toont het instellingenscherm een foutmelding; de overige instellingen worden wel opgeslagen. Een site waar het adres door de oude fout al leeg is, blijft leeg tot er een geldig adres wordt ingevuld.

### 1.15.1

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

Eerst de testgevallen draaien (`php tests/anti-spam-links.php`, `php tests/anti-spam-rapport.php`, `php tests/anti-spam-uitzonderingen.php`, `php tests/anti-spam-naamvelden.php`, `php tests/anti-spam-postcode.php`, `php tests/anti-spam-referrer.php`, `php tests/tijdelijke-accounts.php`, `php tests/tijdelijke-accounts-toolkit.php`, `php tests/afbeeldingstitels.php`, `php tests/afbeeldingstitels-toolkit.php`, `php tests/schema-persoon.php`, `php tests/ai-bots-bezoek.php`, `php tests/font-preload.php`, `php tests/font-preload-toolkit.php` `php tests/einddatum.php`, `php tests/email-afschermen.php`, `php tests/nieuwsbrief-vinkje.php` en `php tests/formuliermonitor-hersteld.php`) en `php -l` op alle bestanden.

1. Versie ophogen op twee plekken in `mediamora-toolkit.php`: `Version:` in de kop en `MM_TOOLKIT_VERSIE`. Of een update wordt aangeboden hangt alleen af van `Version:` in de kop; de constante wordt gelijk gehouden voor de rest, zoals de mailheader van de Formuliermonitor.
2. Bestanden uploaden naar de repo.
3. Release aanmaken met tag gelijk aan de versie, met een v ervoor, bijvoorbeeld `v1.0.1`.

Sites zien de update binnen twaalf uur in de updatelijst en in MainWP.
