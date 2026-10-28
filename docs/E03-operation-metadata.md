# E3 — Métadonnées d'opération et types

**Objectif.** Pour chaque route : savoir lire n'importe quel type PHP (natif ou PHPDoc), retrouver l'action, ses
attributs et sa PHPDoc, et en tirer résumé, description, tags, `operationId`, type de requête et types de réponse.

**Dépend de :** E2.
**Architecture :** [architecture.md](architecture.md) §4.1 et §4.3.

## Livrables

### Système de types (`src/Types/`)

- [x] Arbre `Type` : `NamedType`, `GenericType`, `UnionType`, `IntersectionType`, `NullableType`,
      `ArrayShapeType`, `ListType`, `LiteralType`, `MixedType`
- [x] `TypeResolver` : types natifs, `@return`, `@param`, `@var`, `@property` (`-read`, `-write`, parents compris),
      `@extends` / `@implements`, `@template`, `@throws`, via `phpstan/phpdoc-parser` ; `DocBlock` (résumé,
      description, `@deprecated`)
- [x] Résolution des noms (`use`, groupes `use A\{B, C as D}`, espace de noms, `self`, `static`, `$this`,
      `parent`) et des templates hérités (`@extends BaseResource<User>` → `JsonResource<User>`)
- [x] Génériques Laravel courants : `Collection<int, User>`, `AnonymousResourceCollection<UserResource>`,
      `LengthAwarePaginator<int, User>`, `JsonResource<User>`
- [x] Cache par instance de `TypeResolver` (commentaires analysés, imports des fichiers ; aucun cache statique)

### Action et métadonnées (`src/Extraction/`)

- [x] Résolution de l'action (`OperationContextFactory`) : méthode de contrôleur, `__invoke`, closure ; action
      introuvable → diagnostic et contexte vide
- [x] PHPDoc : résumé (premier paragraphe, coupé après sa première phrase quand elle finit une ligne),
      description (le reste), `@deprecated`
- [x] Attributs de méthode et de classe : la classe donne des valeurs par défaut (champs restés vides), sauf
      `tags` et `deprecated: true` qui s'appliquent à toutes ses actions ; attribut PHP natif `#[\Deprecated]`
      (PHP 8.4+, lu par son nom pour rester compatible 8.3). La sécurité de classe vient avec E7.
- [x] Type de requête : premier paramètre sous-type de `Illuminate\Http\Request` ; les autres paramètres de
      l'action (liaisons de modèles, enums, paramètres scalaires de route) sont typés par la découverte (E2)
- [x] Exceptions déclarées : `@throws` de l'action seulement (unions comprises) → `OperationContext::$throws`,
      lecture désactivable (clé globale `exceptions.read_throws`) ; ni analyse du code appelé, ni `@throws` de la
      `FormRequest`
- [x] Types de réponse : type de retour de la PHPDoc, sinon natif (`OperationContext::$returnType`) ; membres d'une
      union exposés par `returnTypes()`. La réponse par statut, le regroupement en `oneOf` et `#[Response]` sont en E6.

### Stratégies de nommage (`src/Extraction/Strategies/`)

- [x] `SummaryStrategy` : PHPDoc, nom de route, URI, contrôleur (remplace `name-by`)
- [x] `TagStrategy` : contrôleur, URI, nom de route, avec `pattern` (remplace `tag-by`) ; `#[Tag]` lu par
      l'extracteur d'attributs
- [x] `GroupStrategy` : groupes de tags (remplace `group-by`), appliquée par la `TagGroupPass` (E7)
- [x] `OperationIdStrategy` : nom de route, `Contrôleur.méthode`, méthode + chemin (repli des deux premiers) ;
      unicité garantie par une passe (E7)
- [x] Clés de traduction acceptées dans les résumés, descriptions et tags (`TranslationOperationExtractor`, contrat
      `Translator` de Laravel, locale de la génération)

### Documentation externe et tags

- [x] `#[Operation(externalDocs:, externalDocsDescription:)]` → `Operation.externalDocs` (`url`, `description`)
- [x] `#[Tag]` : `description`, `parent`, `summary` et `kind` (3.2), `externalDocs` et `externalDocsDescription`
      → `OperationDescription::$declaredTags`, rassemblés dans le document par la `TagPass` (E7)

## Critères de sortie

- [x] Tests unitaires du `TypeResolver` (un cas par forme de type) et de chaque stratégie
- [x] Scénario fonctionnel « métadonnées » (descriptions extraites) — ses documents attendus viennent avec
      l'écriture (E8)

## Notes de réalisation

- **Ordre des extracteurs** (priorité croissante, chacun affine le précédent) : stratégies de nommage (0), PHPDoc
  (100), attributs (200), traduction (1000). Le résumé PHPDoc passe par la stratégie `doc` ; le texte qui suit est
  toujours la description, quelle que soit la stratégie.
- **Repli des identifiants** : `name` sans nom de route et `action` sur une closure donnent `method_path`
  (`getApiUsersByUser`). Plusieurs méthodes d'une route partagent son nom : la passe `operationId` d'E7 les rend
  uniques.
- **Tags par défaut** : sans `pattern`, `controller` donne le nom court sans `Controller` (`User`), `uri` et `name`
  le premier segment hors `api` et version (`users`). Le `pattern` de la configuration par défaut cible
  `App\Http\Controllers\…Controller`, et garde le sous-espace de noms (`Admin\User`).
- **Stratégie personnalisée** : une classe qui implémente l'interface, construite sans argument ; le fournisseur de
  services la construira par le conteneur (E10).
- **Types** : la PHPDoc l'emporte sur le type natif ; `T[]` est lu comme `list<T>` ; une constante de classe ou un
  cas d'enum adossée (`Status::Draft`, `self::KIND_*`) donne ses valeurs ; un template non lié, un type
  conditionnel non tranché ou une signature de callable deviennent `MixedType`, `callable` ou l'union des branches.
  Les ordres d'union natifs suivent PHP (`int|string` est relu `string|int`).
- Les fixtures `tests/Fixtures/Types/` portent volontairement des PHPDoc que PHPStan refuse : elles sont exclues de
  PHPStan et de PHP-CS-Fixer.

## Décision : `@throws` (2026-10-28)

`@throws` est lu sur l'action seulement. Les rejets d'une `FormRequest` sont déjà couverts sans lui : 403 quand
`authorize()` est redéfinie et 422 quand des règles existent (E7). Un `@throws` sur `authorize()` ou `rules()` est
rare ; une exception levée ailleurs se déclare sur l'action, ou par `#[Response]`. La correspondance exception →
réponse est en E6, la fusion avec les réponses d'erreur en E7.
