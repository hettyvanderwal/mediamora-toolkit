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
| Herroepingsrecht bij afrekenen | uit, alleen met WooCommerce | code in `functions.php` (`mm_withdrawal_text`) |

## Overstappen

Staat de losse versie van een module nog op de site, dan laadt de toolkit die module niet en toont hij een melding. De losse versie blijft dan gewoon werken. Pas als de losse versie weg is, neemt de module het over. Instellingen, logs en optienamen zijn gelijk gebleven, dus er gaat niets verloren.

Bij de eerste activering komt elke module aan waarvan een losse versie op de site staat, ook als die module standaard uit staat.

De opgeslagen keuzes gaan altijd voor op de standaard. Wordt de standaard van een module later omgezet, dan houdt een site die daar zelf een keuze voor heeft opgeslagen gewoon wat hij had.

Herroepingsrecht bij afrekenen vervangt code in `functions.php`. Die laadt na de plugins, dus de module kijkt pas op `init` of de functie `mm_withdrawal_text` bestaat. Zo ja, dan hangt de module geen enkele checkout-, validatie-, opslag-, mail- of beheerhook op en staat er een melding in de instellingen. De module mag al aan staan; hij neemt het over zodra de oude code weg is. De metakey `_mm_withdrawal_waiver` op bestellingen is gelijk gebleven.

REST-gebruikers afschermen ging van standaard aan naar standaard uit. Sites die daar nog geen keuze voor hadden opgeslagen, krijgen die eenmalig alsnog, maar alleen als WooCommerce aanstaat: een webshop houdt de module zo aan. Op een site zonder WooCommerce komt de module uit te staan en is ASE met "Disable REST API" weer de route.

## Vastzetten per site

In `wp-config.php`, bijvoorbeeld op een academie:

```php
define( 'MM_TOOLKIT_MODULE_ALT_TEKSTEN', false );
```

Beschikbaar: `MM_TOOLKIT_MODULE_ALT_TEKSTEN`, `MM_TOOLKIT_MODULE_HERO_PRELOAD`, `MM_TOOLKIT_MODULE_PREVIEW_LINK`, `MM_TOOLKIT_MODULE_ANTI_SPAM`, `MM_TOOLKIT_MODULE_FORMULIERMONITOR`, `MM_TOOLKIT_MODULE_AI_BOTS`, `MM_TOOLKIT_MODULE_REST_USERS`, `MM_TOOLKIT_MODULE_WITHDRAWAL_WAIVER`.

De oude namen zonder `MODULE_` (zoals `MM_TOOLKIT_ALT_TEKSTEN`) werken nog voor de eerste zeven modules, zodat bestaande regels in `wp-config.php` niet aangepast hoeven te worden. Staan beide er, dan wint de nieuwe naam. Een vastgezette module houdt bij het opslaan van het instellingenscherm de keuze die er al stond.

## Deactiveren en verwijderen

Deactiveren haalt alleen de cron-events en het preview-blok uit `.htaccess` weg. Instellingen en logs blijven staan.

Verwijderen via Plugins ruimt alles op: de opties en transients van de toolkit en de modules, de cron-events, de alt-tekstkenmerken (`_mm_alt_*`, de alt-teksten zelf blijven staan), het productvinkje `_mm_withdrawal_waiver_applies` (de tekst `_mm_withdrawal_waiver` op bestellingen blijft altijd staan, dat is het bewijs van afstand), het `.htaccess`-blok en de logmap van de anti-spam met de inzendingen erin. Kan de logmap niet worden weggehaald, dan blijft de optie `mediamora_antispam_log_dir` staan, zodat te vinden is waar hij staat. Staat de losse versie van een module nog op de site, als plugin of mu-plugin en actief of niet, dan blijft alles van die module staan, want de losse versie gebruikt dezelfde gegevens.

## Wijzigingen

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

1. Versie ophogen op twee plekken in `mediamora-toolkit.php`: `Version:` in de kop en `MM_TOOLKIT_VERSIE`.
2. Bestanden uploaden naar de repo.
3. Release aanmaken met tag gelijk aan de versie, met een v ervoor, bijvoorbeeld `v1.0.1`.

Sites zien de update binnen twaalf uur in de updatelijst en in MainWP.
