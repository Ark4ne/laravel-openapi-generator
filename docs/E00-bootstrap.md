# E0 — Branche, socle et outillage

**Objectif.** Une branche `v1.0` vidée du code v0.6, avec les dépendances à jour, les outils de qualité de
`openapi-writer` et une CI qui tourne à chaque push.

**Dépend de :** rien.
**Architecture :** [architecture.md](architecture.md) §2 (couches) et §12 (garde-fous).

## Préalable

Fait : les modifications en attente de la v0.6 sont commitées sur `v0.6` (`e7d08dc`), et `v1.0` repart de ce commit.

## Livrables

### Nettoyage

- [x] Retrait de `src/`, `tests/`, `config/` et de `phpunit.xml.dist` v0.6 (l'historique reste sur `v0.6`)
- [x] `.gitignore` complété (`.DS_Store`, `build/`, `.phpunit.cache/`, `tests/**/dist/`)

### Dépendances (`composer.json`)

- [x] `php ^8.3`, `ext-json`
- [x] Composants `illuminate/*` `^13.0` nécessaires (`console`, `contracts`, `database`, `filesystem`, `http`,
      `routing`, `support`, `validation`) au lieu de `laravel/framework`
- [x] `ark4ne/openapi-writer ^1.0` (dépôt `path` vers `../openapi-writer` tant que le paquet n'est pas publié)
- [x] `phpstan/phpdoc-parser ^2.3`
- [x] Retrait de `goldspecdigital/oooas`, `phpdocumentor/*`, `justinrainbow/json-schema`
- [x] `suggest` : `ark4ne/laravel-json-api ^1.9`, `symfony/yaml`, `opis/json-schema`
- [x] `require-dev` : `orchestra/testbench ^11.0`, `phpunit/phpunit ^12.5 || ^13.0`, `phpstan/phpstan ^2.1`,
      `larastan/larastan ^3.13`, `phpstan/phpstan-strict-rules`, `phpstan/phpstan-phpunit`, `phpat/phpat`,
      `spaze/phpstan-disallowed-calls`, `friendsofphp/php-cs-fixer`, `opis/json-schema`, `symfony/yaml`,
      `ark4ne/laravel-json-api`

### Outils

- [x] PHPUnit strict (échec sur test incomplet, ignoré, risqué, avertissement, notice, dépréciation), suites
      `Unit`, `Feature`, `Architecture`
- [x] PHPStan niveau max sur `src/`, `tests/`, `bin/` avec Larastan, règles strictes, `phpstan-phpunit`, `phpat`,
      `disallowed-calls` (façades et helpers interdits hors de `src/Laravel/`)
- [x] PHP-CS-Fixer en PER-CS (`.php-cs-fixer.dist.php`)
- [x] Infection (`infection.json5`, MSI minimum 80 %) et `bin/coverage-check.php` (90 % des lignes)
- [x] `bin/progress.php` (repris du writer, lit `docs/E*.md`) et `--check` dans la CI
- [x] Scripts Composer : `test`, `test:coverage`, `stan`, `cs`, `cs:fix`, `mutation`, `progress`, `fixtures:update`, `ci`

### CI (`.github/workflows/ci.yml`, remplace `php.yml`)

- [x] Jobs `tests`, `static`, `coverage`, `mutation`, `audit` ; matrice PHP 8.3, 8.4, 8.5 × dépendances `lowest` et `highest`
- [x] Récupération d'`ark4ne/openapi-writer` dans la CI tant qu'il n'est pas sur Packagist
- [x] Déclenchement sur `push` et sur chaque pull request

### Documentation

- [x] `docs/` : feuille de route, architecture, inventaire v0.6, un document par épique
- [x] `CLAUDE.md` réécrit pour la V1 (commandes, couches, cycle contrat → tests → code, skill du writer)
- [x] `README.md` (statut « V1 en cours »), `CHANGELOG.md`, `CONTRIBUTING.md`, `.editorconfig`, `.gitattributes`

## Critères de sortie

- [x] `composer ci` passe en local (PHP 8.5, dépendances au plus haut et au plus bas)
- [ ] CI verte sur GitHub pour toute la matrice

## Notes

- Vérifié en local sur PHP 8.5 : dépendances au plus haut (Laravel 13.39, PHPUnit 13.4) et au plus bas (Laravel 13.0.0,
  PHPUnit 12.5.8, PHPStan 2.3.0) ; les règles `phpat` et `disallowed-calls` lèvent bien leurs erreurs sur un fichier sonde.
- La CI récupère `ark4ne/openapi-writer` depuis GitHub (`main`) et le place dans `../openapi-writer` : elle échouera
  tant que ce dépôt n'existe pas.
- `codecov.yml` et l'envoi à Codecov sont retirés : le seuil est vérifié par `bin/coverage-check.php`, comme dans le writer.
- `LICENSE` contient la GPL-3.0 alors que `composer.json` déclare MIT (déjà le cas en v0.6) : à trancher avant la 1.0.

- Laravel 14 est attendu au premier trimestre 2027 : l'ajouter à la contrainte et à la matrice dès sa sortie.
- `ark4ne/openapi-writer` n'a ni dépôt distant ni tag : sa publication en 1.0.0 bloque la release du générateur (E11).
