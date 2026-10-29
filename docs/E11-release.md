# E11 — Migration, documentation et version 1.0.0

**Objectif.** Publier la 1.0.0 avec un guide de migration depuis la v0.6, une documentation complète et un skill
pour les agents.

**Dépend de :** E0 à E10.

## Livrables

### Dépendances

- [ ] `ark4ne/openapi-writer` 1.0.0 publié sur Packagist ; dépôt `path` retiré de `composer.json`
- [x] Contraintes vérifiées au plus bas et au plus haut ; Laravel 14 ajouté s'il est sorti

### Documentation

- [x] `UPGRADE.md` : configuration v0.6 → V1 (table de E1), balises `@oa-*` → attributs, parsers → extracteurs,
      transformateurs → `MiddlewareMapper`, `DescribableRequest`
- [x] `README.md` réécrit : installation, configuration, attributs, extensions, FAQ
- [x] Skill pour agents `skills/laravel-openapi-generator/` (en anglais, livré dans le paquet, exemples testés),
      sur le modèle de celui du writer
- [x] Skill de migration `skills/laravel-openapi-generator-upgrade/` : procédure v0.6 → V1, correspondance complète,
      portages testés
- [x] `CHANGELOG.md` de la 1.0.0

### Qualité

- [ ] Couverture ≥ 90 % des lignes, MSI ≥ 80 %
- [ ] CI verte sur toute la matrice
- [x] Génération vérifiée sur une application réelle (Sanctum + JSON:API), documents validés en 3.0, 3.1 et 3.2

### Publication

- [ ] Branche `v1.0` fusionnée dans `master`, tag `v1.0.0`
- [ ] Politique de maintenance de la v0.6 décidée et annoncée

## Critères de sortie

- [ ] `composer require ark4ne/laravel-openapi-generator:^1.0` fonctionne sur un projet Laravel 13 neuf

## Notes de réalisation

- **Contraintes.** Les contraintes sont vérifiées au plus bas (`--prefer-lowest`) et au plus haut à chaque épique.
  Laravel 14 n'est pas sorti au 29/10/2026 (dernière version : 13.39).
- **`UPGRADE.md`.**
  - Il reprend la table de configuration de l'E1, en anglais.
  - Il couvre aussi les quatre balises de la v0.6 (`@oa-description`, `@oa-response-*`) et les arguments de son
    `#[Response]`, avec leurs remplaçants.
  - Il traite `DescribableRequest` → `DescribesRequest`, `CustomRuleParser` → `DescribesRule`, les parsers et
    transformateurs → les points d'extension, et ce qui change dans les documents.
- **README.** Il est réécrit : ce que le générateur lit et écrit, attributs, requêtes, erreurs, webhooks (et comment
  ne pas les servir), JSON:API, extensions, commandes, configuration, skill, FAQ.
- **Skill.** `skills/laravel-openapi-generator/` est livré dans le paquet : `SKILL.md`, `reference.md`, `recipes.md`.
  `tests/Feature/Skill/SkillTest.php` vérifie que :
  - chaque recette s'exécute dans une application et donne un document valide en 3.0, 3.1 et 3.2, avec des assertions
    propres à chaque recette ;
  - la référence nomme chaque attribut et chacun de ses paramètres, chaque clé de configuration et chaque point
    d'extension ;
  - les liens entre fichiers se résolvent, ancres comprises.
  - Les recettes s'exécutent comme `routes/api.php` (préfixe `api`) avec la configuration publiée : ce qui ne serait pas
    documenté par défaut fait échouer le test.
- **Skill de migration.** `skills/laravel-openapi-generator-upgrade/` : `SKILL.md` (procédure en dix étapes, de la
  référence v0.6 à la comparaison des documents), `mapping.md` (chaque clé, balise, attribut, classe et clé de
  traduction de la v0.6), `examples.md` (code v0.6 et son portage). `tests/Feature/Skill/UpgradeSkillTest.php`
  exécute chaque portage en 3.0, 3.1 et 3.2 et vérifie que la correspondance nomme tout ce que la v0.6 exposait ; les
  aides communes aux deux skills sont dans `SkillTestCase`.
  - La relecture de la v0.6 a corrigé `UPGRADE.md` : la v0.6 lisait quatre balises (`@oa-description`,
    `@oa-response-status`, `@oa-response-paginate`, `@oa-response-header`), pas une seule ; son résumé venait de la
    stratégie `name-by` et sa description de la première ligne de la PHPDoc ; ses clés de traduction fixes
    (`openapi.requests.*`) ne sont plus lues.
  - Pièges notés : une balise `@oa-*` restante est ignorée sans avertissement ; un `#[Response(<statut>)]` sans
    `resource:` ni `type:` n'a pas de corps quand rien n'est inféré pour ce statut.
- **Webhooks.** Leurs routes doivent correspondre à `routes.include` et être protégées par un middleware qui refuse
  toute requête. Les enregistrer seulement en console ne suffit pas : `route:cache` s'exécute aussi en console.
- **Couverture.** 92,37 % des lignes. Depuis PHPUnit 12, seules les classes déclarées par `#[CoversClass]` /
  `#[CoversNamespace]` sont mesurées ; les tests qui exercent les objets de description, les schémas de sécurité, les
  portées, le chargeur de relations, les valeurs de configuration, les réponses partagées, les squelettes JSON:API et
  les types MIME les déclarent désormais (87,75 % avant).
- **Mutation.** Le MSI n'est pas mesuré : Infection n'est pas installé sur la machine de développement (`composer
  mutation` l'attend globalement). Ce point reste ouvert.
- **Application réelle.** Squelette `laravel/laravel` 13.17, Sanctum (`install:api`), `ark4ne/laravel-json-api` 1.9
  et `opis/json-schema` :
  - un modèle `Post` avec fabrique et énumération, ressources JSON:API avec relations, requêtes `Includes`, `Fields` et
    `Rule::enum()`, routes publiques et protégées (`auth:sanctum`, `throttle:api`) ;
  - trois documents (3.0.4 avec `redoc`, 3.1.2, 3.2.0) écrits et validés contre les schémas officiels, sans
    avertissement ; `openapi:generate --strict --dry-run` réussit.
  - Constat : les règles validées dans le contrôleur (`$request->validate()`) ne sont pas lues ; le README et le
    skill le disent.
- **Restent ouverts**, hors de portée de cette session :
  - la publication du writer sur Packagist et le retrait du dépôt `path` ;
  - la CI sur GitHub (rien n'est poussé) et la mesure du MSI ;
  - la fusion dans `master`, le tag `v1.0.0` et la politique de maintenance de la v0.6 ;
  - `composer require …:^1.0` sur un projet neuf, qui attend la publication.

