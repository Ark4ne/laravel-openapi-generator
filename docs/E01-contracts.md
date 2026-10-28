# E1 — Architecture et contrats

**Objectif.** Toutes les interfaces, les objets de description, le contexte, la configuration et les attributs
publics existent en signatures et en PHPDoc, avec des corps neutres. L'API est relue avant les tests des épiques
suivants.

**Dépend de :** E0.
**Architecture :** [architecture.md](architecture.md) §2 à §8.

## Livrables

### Couches et garde-fous

- [ ] Dossiers des couches et règles `phpat` : dépendances, `final`, aucun trait, nommage des `*Extractor`,
      `*RuleMapper`, `*Sampler`, `*Pass`
- [ ] Règles `disallowed-calls` : `config()`, `app()`, `resolve()`, `request()`, façades, `dd()`, `dump()` hors de `Laravel/`

### Description (`src/Description/`)

- [ ] Base `Description` (`copy()`) et énumérations propres (`ParameterLocation` du writer réutilisée si possible)
- [ ] `DocumentDescription`, `OperationDescription`, `RequestDescription`, `ParameterDescription`,
      `BodyDescription`, `FieldDescription`, `ResponseDescription`, `HeaderDescription`, `SecurityDescription`,
      `TagDescription`
- [ ] Setters qui remplacent et méthodes `add*` qui ajoutent sur toutes les collections

### Contexte et diagnostics (`src/Context/`)

- [ ] `GenerationContext` (configuration du document, diagnostics, composants, locale)
- [ ] `Diagnostics`, `Diagnostic`, `DiagnosticLevel` (`Info`, `Warning`, `Error`) avec route et étape
- [ ] `ComponentRegistry` (enregistrement par nom, détection des collisions)

### Points d'extension

- [ ] `OperationExtractor`, `RequestExtractor`, `RuleMapper`, `ResponseExtractor` et leurs registres à priorités
- [ ] `Sampler`, `Samplers`, `SamplingSession`, `SchemaInferrer`
- [ ] `Pass`, `Passes::default()`, `MiddlewareMapper`
- [ ] Stratégies : `SummaryStrategy`, `TagStrategy`, `GroupStrategy`, `OperationIdStrategy`, `QuerySerialization`
- [ ] `DescribesRule` : interface qu'une règle de validation de l'application implémente pour se décrire

### Configuration (`src/Configuration/`, `config/openapi.php`)

- [ ] `Configuration`, `DocumentConfiguration` (info, serveurs, routes, version OpenAPI, mode strict, sortie,
      stratégies, langues), construits depuis le tableau et validés
- [ ] Nouveau `config/openapi.php` : `documents`, `output`, `sampling`, `middlewares`, `extensions`, `languages`
- [ ] Table de correspondance avec la configuration v0.6, pour le guide de migration (E11)

### Attributs publics (`src/Attributes/`)

- [ ] `#[Operation]` (summary, description, operationId, tags, deprecated), `#[Tag]`, `#[Exclude]`
- [ ] `#[Response]` répétable (status, description, resource ou schéma, type de média, en-têtes, paginateur)
- [ ] `#[PathParameter]`, `#[QueryParameter]`, `#[Header]`, `#[RequestBody]`, `#[Security]`
- [ ] `#[ResourceFactory]`, `#[SchemaName]` (remplace `#[Id]`)

### Erreurs (`src/Exceptions/`)

- [ ] `OpenApiGeneratorException` (interface), `InvalidConfigurationException`, `InvalidAttributeException`,
      `GenerationFailedException` (mode strict)

## Critères de sortie

- [ ] PHPStan niveau max vert, tests d'architecture verts
- [ ] API relue et validée (PR approuvée)

## Questions à clore pendant E1

- Noms des attributs : `#[Operation]` entre en collision avec `Ark4ne\OpenApiWriter\Objects\Operation` dans les
  fichiers qui importent les deux ; préfixer (`#[OpenApiOperation]`) ou documenter l'alias ?
- Enregistrement des extensions : configuration seule, ou aussi tags du conteneur pour les paquets tiers ?
