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

## Overstappen

Staat de losse versie van een module nog op de site, dan laadt de toolkit die module niet en toont hij een melding. De losse versie blijft dan gewoon werken. Pas als de losse versie weg is, neemt de module het over. Instellingen, logs en optienamen zijn gelijk gebleven, dus er gaat niets verloren.

Bij de eerste activering komt elke module aan waarvan een losse versie op de site staat, ook als die module standaard uit staat.

De opgeslagen keuzes gaan altijd voor op de standaard. Wordt de standaard van een module later omgezet, dan blijft een site die hem al aan had staan hem gewoon houden.

## Vastzetten per site

In `wp-config.php`, bijvoorbeeld op een academie:

```php
define( 'MM_TOOLKIT_ALT_TEKSTEN', false );
```

Beschikbaar: `MM_TOOLKIT_ALT_TEKSTEN`, `MM_TOOLKIT_HERO_PRELOAD`, `MM_TOOLKIT_PREVIEW_LINK`, `MM_TOOLKIT_ANTI_SPAM`, `MM_TOOLKIT_FORMULIERMONITOR`, `MM_TOOLKIT_AI_BOTS`, `MM_TOOLKIT_REST_USERS`.

## Release maken

1. Versie ophogen op twee plekken in `mediamora-toolkit.php`: `Version:` in de kop en `MM_TOOLKIT_VERSIE`.
2. Bestanden uploaden naar de repo.
3. Release aanmaken met tag gelijk aan de versie, met een v ervoor, bijvoorbeeld `v1.0.1`.

Sites zien de update binnen twaalf uur in de updatelijst en in MainWP.
