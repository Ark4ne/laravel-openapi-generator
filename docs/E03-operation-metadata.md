# E3 — Métadonnées d'opération et types

**Objectif.** Pour chaque route : savoir lire n'importe quel type PHP (natif ou PHPDoc), retrouver l'action, ses
attributs et sa PHPDoc, et en tirer résumé, description, tags, `operationId`, type de requête et types de réponse.

**Dépend de :** E2.
**Architecture :** [architecture.md](architecture.md) §4.1 et §4.3.

## Livrables

### Système de types (`src/Types/`)

- [ ] Arbre `Type` : `NamedType`, `GenericType`, `UnionType`, `IntersectionType`, `NullableType`,
      `ArrayShapeType`, `ListType`, `LiteralType`, `MixedType`
- [ ] `TypeResolver` : types natifs, `@return`, `@param`, `@var`, `@property`, `@extends` / `@implements`,
      `@template`, via `phpstan/phpdoc-parser`
- [ ] Résolution des noms (`use`, espace de noms, `self`, `static`, `parent`) et des templates hérités
- [ ] Génériques Laravel courants : `Collection<int, User>`, `AnonymousResourceCollection<UserResource>`,
      `LengthAwarePaginator<int, User>`, `JsonResource<User>`
- [ ] Cache par instance de `TypeResolver` (aucun cache statique)

### Action et métadonnées (`src/Extraction/Operations/`)

- [ ] Résolution de l'action : méthode de contrôleur, `__invoke`, closure (réflexion de la closure)
- [ ] PHPDoc : résumé (première phrase), description (reste), `@deprecated`
- [ ] Attributs de méthode et de classe (la classe donne les valeurs par défaut : tags, sécurité), attribut PHP
      natif `#[\Deprecated]` (PHP 8.4+) pris en compte
- [ ] Type de requête : premier paramètre sous-type de `Illuminate\Http\Request` ; autres paramètres de l'action
      (liaisons de modèles, enums, paramètres scalaires de route)
- [ ] Exceptions déclarées : `@throws` de l'action seulement (unions comprises) → `OperationContext::$throws`,
      lecture désactivable (clé globale `exceptions.read_throws`) ; ni analyse du code appelé, ni `@throws` de la
      `FormRequest`
- [ ] Types de réponse : type de retour natif, puis PHPDoc, puis `#[Response]` ; une union donne une réponse par
      statut (membres de même statut réunis en `oneOf`, voir [spec-coverage.md](spec-coverage.md))

### Stratégies de nommage

- [ ] `SummaryStrategy` : PHPDoc, nom de route, URI, contrôleur (remplace `name-by`)
- [ ] `TagStrategy` : contrôleur, préfixe d'URI, nom de route, attribut `#[Tag]`, expression régulière (remplace `tag-by`)
- [ ] `GroupStrategy` : groupes de tags (remplace `group-by`)
- [ ] `OperationIdStrategy` : nom de route, `Contrôleur.méthode`, méthode + chemin ; unicité garantie par une passe (E7)
- [ ] Clés de traduction acceptées dans les résumés, descriptions et tags

### Documentation externe et tags

- [ ] `#[Operation(externalDocs:, externalDocsDescription:)]` → `Operation.externalDocs` (`url`, `description`)
- [ ] `#[Tag]` : `description`, `parent`, `summary` et `kind` (3.2), `externalDocs` et `externalDocsDescription`

## Critères de sortie

- [ ] Tests unitaires du `TypeResolver` (un cas par forme de type) et de chaque stratégie
- [ ] Scénario fonctionnel « métadonnées » avec documents attendus

## Décision : `@throws` (2026-10-28)

`@throws` est lu sur l'action seulement. Les rejets d'une `FormRequest` sont déjà couverts sans lui : 403 quand
`authorize()` est redéfinie et 422 quand des règles existent (E7). Un `@throws` sur `authorize()` ou `rules()` est
rare ; une exception levée ailleurs se déclare sur l'action, ou par `#[Response]`. La correspondance exception →
réponse est en E6, la fusion avec les réponses d'erreur en E7.
