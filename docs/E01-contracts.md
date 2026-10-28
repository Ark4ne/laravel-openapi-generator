# E1 — Architecture et contrats

**Objectif.** Toutes les interfaces, les objets de description, le contexte, la configuration et les attributs
publics existent en signatures et en PHPDoc, avec des corps neutres. L'API est relue avant les tests des épiques
suivants.

**Dépend de :** E0.
**Architecture :** [architecture.md](architecture.md) §2 à §8.

## Livrables

### Couches et garde-fous

- [x] Dossiers des couches et règles `phpat` : dépendances, `final`, aucun trait, nommage des `*Extractor`,
      `*RuleMapper`, `*Sampler`, `*Pass`
- [x] Règles `disallowed-calls` : `config()`, `app()`, `resolve()`, `request()`, façades, `dd()`, `dump()` hors de `Laravel/`

### Description (`src/Description/`)

- [x] Base `Description` (`copy()`), enum `ResponseOrigin` ; enums du writer réutilisées (`ParameterLocation`, `HttpMethod`…)
- [x] `DocumentDescription`, `OperationDescription`, `RequestDescription`, `ParameterDescription`,
      `BodyDescription`, `FieldDescription`, `ResponseDescription`, `HeaderDescription`, `SecurityDescription`,
      `TagDescription` (`SecurityScheme` du writer utilisé tel quel pour les composants)
- [x] Setters qui remplacent et méthodes `add*` qui ajoutent sur toutes les collections

### Contexte et diagnostics (`src/Context/`)

- [x] `GenerationContext` (configuration du document, diagnostics, composants, locale)
- [x] `Diagnostics`, `Diagnostic`, `DiagnosticLevel` (`Info`, `Warning`, `Error`) avec route et étape
- [x] `ComponentRegistry` (enregistrement par nom, détection des collisions)

### Points d'extension

- [x] `OperationExtractor`, `RequestExtractor`, `RuleMapper`, `ResponseExtractor` et leurs registres à priorités
- [x] `Sampler`, `Samplers`, `SamplingSession`, `SchemaInferrer`
- [x] `Pass`, `Passes`, `MiddlewareMapper` (la chaîne par défaut `Passes::default()` vient avec E7)
- [x] Stratégies : `SummaryStrategy`, `TagStrategy`, `GroupStrategy`, `OperationIdStrategy`, `QueryStrategy`
- [x] `DescribesRule` : interface qu'une règle de validation de l'application implémente pour se décrire

### Configuration (`src/Configuration/`, `config/openapi.php`)

- [x] `Configuration`, `DocumentConfiguration` (info, serveurs, routes, version OpenAPI, mode strict, sortie,
      stratégies, langues), construits depuis le tableau et validés
- [x] Nouveau `config/openapi.php` : `documents`, `output`, `sampling`, `middlewares`, `extensions`, `languages`
- [x] Table de correspondance avec la configuration v0.6, pour le guide de migration (E11)

### Attributs publics (`src/Attributes/`)

- [x] `#[Operation]` (summary, description, operationId, tags, deprecated), `#[Tag]`, `#[Exclude]`
- [x] `#[Response]` répétable (status, description, resource ou schéma, type de média, en-têtes, paginateur)
- [x] `#[PathParameter]`, `#[QueryParameter]`, `#[Header]`, `#[RequestBody]`, `#[Security]`
- [x] `#[ResourceFactory]`, `#[SchemaName]` (remplace `#[Id]`)
- [x] `#[Webhook]` et sa définition (détail dans [E12](E12-webhooks.md))
- [x] Contrats tirés des épiques suivants pour typer les signatures : `Types\Type` (E3), `Discovery\RouteDefinition` (E2),
      `Extraction\OperationContext`, `Sampling\SampleRequest`

### Erreurs (`src/Exceptions/`)

- [x] `OpenApiGeneratorException` (interface), `InvalidConfigurationException`, `InvalidAttributeException`,
      `GenerationFailedException` (mode strict)

## Critères de sortie

- [x] PHPStan niveau max vert, tests d'architecture verts
- [ ] API relue et validée (PR approuvée)

## Notes de réalisation

- Corps neutres : les registres conservent leurs éléments mais ne les trient ni ne les appliquent, `Configuration`
  et ses sous-objets ne lisent pas le tableau, `Description::copy()` renvoie l'objet lui-même. PHPStan ignore
  `return.unusedType` dans `src/` tant que ces corps restent neutres (`phpstan.neon.dist`).
- Deux modes de registre (architecture §4.1) : opérations et requêtes enrichissent (tous les extracteurs, priorité
  croissante), réponses et samplers choisissent (le premier, priorité décroissante). `priority()` renvoie une
  constante `PRIORITY`.
- `MiddlewareMapper::map()` reçoit et renvoie aussi le document, qui accueille les composants partagés.
- `RequestDescription` porte des `FieldDescription` encore non placés : la `QueryStrategy` ou le corps les placent.
- Configuration en `snake_case` ; correspondance avec la v0.6 dans [configuration-migration.md](configuration-migration.md).
- Tests d'architecture ajoutés : nommage des implémentations (`*Extractor`, `*RuleMapper`, `*Sampler`, `*Pass`,
  `*MiddlewareMapper`, `*Strategy`), `Exceptions` autorisée à dépendre des diagnostics.

## Questions closes

- `#[Operation]` garde son nom ; la collision avec `Ark4ne\OpenApiWriter\Objects\Operation` se règle par un alias
  d'import, documenté dans le README (cas réservé au code d'extension).
- Extensions enregistrées par la configuration `extensions` **et** par les tags du conteneur (paquets tiers) : E10.
