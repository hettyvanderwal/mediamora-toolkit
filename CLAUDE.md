# Mediamora Toolkit

## Controles

Elke push en pull request draait de workflow Controles (`.github/workflows/controles.yml`):
PHPCS, PHPStan, Semgrep, gitleaks, Inhoud releasezip en de testgevallen in `tests/` op PHP 7.4 en 8.3.

- Werk via een branch en een pull request. Op `main` staat een ruleset: alleen via een pull request, alle checks van Controles verplicht groen, geen force-push en niet verwijderen.
- Mergen naar `main` en een release maken alleen als alle checks van die commit groen zijn. Controleer dat met `gh run list --branch <branch>` of `gh pr checks`, niet op gevoel.
- Een check die faalt los je op in de code. Gaat het om vals alarm, dan negeer je die ene regel inline met de reden erbij: `// phpcs:ignore Sniff.Naam -- reden` of `// nosemgrep: regel-id`, met de reden in een commentaarregel erboven. Nooit een sniff, regel of hele check uitzetten in `phpcs-security.xml.dist`, `phpstan.neon.dist` of de workflow.
- Blokkerend in PHPCS (`phpcs-security.xml.dist`): `WordPress.Security.*`, `WordPress.DB.PreparedSQL*`, `WordPress.PHP.DiscouragedPHPFunctions`, `WordPress.PHP.DevelopmentFunctions`, `WordPress.WP.AlternativeFunctions` en `PHPCompatibilityWP`, zowel errors als warnings. De volledige standaard WordPress (`phpcs.xml.dist`) is alleen een rapport.
- PHPStan draait op level 5 met `phpstan-baseline.neon` en kent WooCommerce en Elementor (ook Pro) via de stubs in `require-dev`. Nieuwe meldingen niet in de baseline zetten; de baseline mag alleen kleiner worden.

Lokaal, na `composer install`:

```
vendor/bin/phpcs --standard=phpcs-security.xml.dist
vendor/bin/phpcs --standard=phpcs.xml.dist --report=source
vendor/bin/phpstan analyse --memory-limit=1G
```

## Releasezip

De updater gebruikt de zipball van GitHub en de bijlage `mediamora-toolkit.zip` wordt met `git archive` gemaakt. Wat niet in de zip hoort (tests, `.github`, Composer, PHPCS, PHPStan, dit bestand) staat als `export-ignore` in `.gitattributes`. Een nieuw ontwikkelbestand daar ook bijzetten; de check Inhoud releasezip vangt het anders op.

Ontwikkeltools alleen in `require-dev`. De plugin zelf heeft geen Composer nodig.
