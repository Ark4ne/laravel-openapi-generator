# E10 — Intégration Laravel et expérience développeur

**Objectif.** Un paquet qui s'installe, se configure et se débogue comme un paquet Laravel moderne : fournisseur
de services, configuration publiable, commandes claires, extensions faciles à brancher.

**Dépend de :** E8 (E9 pour l'enregistrement de l'intégration).
**Architecture :** [architecture.md](architecture.md) §2 (Laravel) et §8.

## Livrables

### Fournisseur (`src/Laravel/`)

- [x] `OpenApiServiceProvider` (découverte automatique) : configuration fusionnée et publiable
      (`vendor:publish --tag=openapi-config`), registres construits depuis la configuration
- [x] Extensions de l'application : extracteurs, mappers de règles, samplers, passes, stratégies, déclarés dans
      `extensions`, et par les tags du conteneur (`openapi.response-extractors`…) pour les paquets tiers
- [x] Entrée dans `php artisan about`

### Commandes

- [x] `openapi:generate {--document=*} {--strict} {--dry-run} {--format=} {--stdout}` : sortie par
      `$this->components` (tâches, avertissements groupés par route), codes de retour documentés
- [x] `openapi:inspect {route}` : ce que le générateur a compris d'une route (description, diagnostics), pour
      déboguer une documentation inattendue
- [x] Verbosité : `-v` affiche les diagnostics `Info`, `-q` n'affiche que les erreurs

### Configuration

- [x] Chargement complet de `config/openapi.php` : `Configuration::fromArray()` et `DocumentConfiguration::fromArray()`
      lisent toutes les clés (info, serveurs, version, mode strict, sortie, échantillonnage, middlewares, extensions,
      langues…)
- [x] Erreurs de configuration explicites au chargement (clé, valeur attendue, valeur reçue)
- [x] Chaque clé documentée dans `config/openapi.php` et dans le README

### Performance

- [x] Mesure sur une application de 500 routes (`composer benchmark`) : temps et mémoire consignés dans les décisions

## Critères de sortie

- [x] Tests des commandes (Testbench) et du fournisseur
- [x] Installation vérifiée sur un squelette Laravel 13 neuf (`composer require`, `openapi:generate`)

## Hors périmètre V1

- Interface de consultation (Swagger UI, Scalar, Redoc) et route qui sert le document : laissées à des paquets dédiés.

## Notes de réalisation

- **Lecture de la configuration.** Elle a lieu quand une commande s'exécute, jamais au démarrage de l'application :
  une clé invalide fait échouer la commande (code 2), pas l'application.
  - Une clé de premier niveau inconnue est refusée, avec la liste des clés attendues. `versions` de la v0.6 est
    concerné.
  - `use_references` doit être un booléen.
  - Les classes de `middlewares` et `extensions` doivent exister. Leur interface est vérifiée à la résolution par le
    conteneur (`ExtensionsResolver`), la couche `Configuration` ne connaissant pas les interfaces des couches à sa
    droite.
- **Nouvelle clé `integrations.json_api.errors`.** Elle active les erreurs JSON:API de l'E9 (désactivées par défaut).
- **Résolution par le conteneur.**
  - `GeneratorFactory` construit le générateur d'une configuration lue une seule fois par commande : session
    d'échantillonnage, intégrations, extensions.
  - Les mappers de `middlewares`, les extensions et les stratégies sont tous construits par le conteneur. Une interface
    liée dans le conteneur est acceptée comme une classe.
  - Avant de générer, `Generator::prepare()` construit ce que la configuration nomme pour chaque document (classes de
    stratégie, mappers de middlewares). Une classe du mauvais type donne le code 2 sans qu'aucun document soit écrit.
  - Un service tagué du mauvais type est signalé par son tag (`tag openapi.passes: …`), pas par une clé de la
    configuration.
- **Extensions.** `GeneratorExtensions` (couche `Generation`) porte les instances : les extracteurs et les samplers
  rejoignent leur registre par priorité (`with()`), les mappers de règles remplacent ceux des mêmes règles, les
  passes s'exécutent après les passes intégrées, avant le nettoyage. Les classes de stratégie (`strategies.*.by`) sont
  construites par le conteneur, leurs dépendances injectées.
- **Échantillonnage.** Le fournisseur n'ouvre de transaction que sur la connexion par défaut : les autres connexions
  configurées (`mysql`, `pgsql`… d'un squelette Laravel) ne sont souvent pas joignables là où les documents sont
  générés.
- **`openapi:generate`.**
  - Codes de retour :
    - `0` : tous les documents sont écrits ;
    - `1` : un document a une erreur (ou un avertissement avec `--strict`) et n'est pas écrit ;
    - `2` : la configuration ou les options sont invalides.
  - Sérialisation : le document n'est sérialisé qu'une fois (`DocumentWriter::store()`) pour ne pas dupliquer les
    diagnostics de l'écriture.
  - `--format` change aussi l'extension du fichier (`.json` ↔ `.yaml`).
  - `--stdout` n'accepte qu'un document et une langue. La sortie standard ne reçoit que le document, même avec `-q` ;
    la ligne d'échec et les diagnostics vont sur la sortie d'erreur.
- **`openapi:inspect`.**
  - La route est désignée par son nom, résolu par le routeur (la description ne garde que le libellé
    `GET /path`), par son URI, ou par sa méthode et son URI.
  - Une route nommée à paramètres optionnels vise aussi ses chemins sans eux (`api/users/{user?}` → `/api/users/{user}`
    et `/api/users`).
  - La commande affiche l'opération après les passes, avec les paramètres partagés de son chemin, et tous ses
    diagnostics quelle que soit la verbosité. Elle retrouve aussi ceux enregistrés sous le libellé d'origine de la
    route, avant qu'une passe renomme son chemin.
- **Corrections du cœur découvertes en route.**
  - Un document sans opération garde des `paths` vides. Sans eux, le document était invalide, en 3.0 comme en 3.1. En
    3.0, quand il n'a que des webhooks, le writer écrit lui-même ces `paths` à côté des `x-webhooks` (test de
    régression).
  - `TypeResolver` lit les noms de nœuds de `phpstan/phpdoc-parser` 2.4. Cette version type `NameNode` à côté
    d'`IdentifierTypeNode` ; `composer.lock` n'étant pas versionné, elle est arrivée par une mise à jour.
- **Mesure.** Un document de 500 routes (ressources, collections paginées, requêtes, exceptions, sécurité) est
  généré, validé et écrit en 0,43 s, avec un pic de 68,7 Mo pour le processus entier (PHP 8.5, SQLite en mémoire,
  machine de développement).
- **Installation.** Elle a été vérifiée sur un squelette `laravel/laravel` 13.17 neuf, par dépôts `path` (le writer
  n'étant pas encore sur Packagist) : `composer require`, `vendor:publish --tag=openapi-config`, `about`,
  `openapi:generate` (document écrit sur le disque par défaut) et `openapi:inspect users.show`.
