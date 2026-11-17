# RFC — `ark4ne/laravel-api-revisions` and `ark4ne/laravel-json-api` on it

Status: **draft** (2026-11-14), written from the `laravel-json-api` study · Extends:
[`document-integrations.md`](document-integrations.md) (EI1, EI2 kept; EI3 reworked) · Related:
`laravel-api-revisions/docs/specs/consumers.md`, `laravel-json-api/docs/rfc/api-revisions-integration.md`

## 1. Goal

The generator stays independent: neither library is required. It documents, one document per revision:

1. an application using `laravel-api-revisions` alone (`RevisionedResource`, `RevisionedRequest`);
2. an application using `laravel-json-api` built on it;
3. an application using neither, or `laravel-json-api` 1.9 / 2.0, exactly as today.

The revision model of `laravel-json-api` 2.1 is never tagged: EI3 (`JsonApiV2\Revisions`, the `json_api.revision`
setting, the `Revised` rules) is reworked on the new contract before 1.2.0.

```php
'documents' => [
    'winter-25' => [
        'info' => ['title' => 'API', 'version' => 'winter-25'],
        'integrations' => [
            'json_api' => ['revision' => App\Http\ApiRevision::Winter25],
        ],
    ],
],
```

### Decisions

| # | Decision |
|---|---|
| D1 | **A new integration `ApiRevisions`** (`src/Integrations/ApiRevisions/`, key `api_revisions`), available when `Ark4ne\ApiRevisions\Revisions\Revision` exists. It never knows `since()` or `was()`: it reads the export. |
| D2 | **The revision of a document is `json_api.revision`** (decided 2026-11-17, consistent with 2.1): a case of the enum of `revisions.enum`, read by `JsonApiV3\Revisions::of($context)`. Without the setting, the head. The key of an application of `ark4ne/laravel-api-revisions` without `laravel-json-api` (D1) is open. |
| D3 | **Requests are prepared in the revision**: `prepareRequest()` sets the base of the `RevisionScope`, so `current()`, the rules of a `RevisionedRequest` and every sample render at the revision of the document. |
| D4 | **Schemas come from the export, not from sampling**, for a class implementing `Revisioned`: `JsonSchemaExporter::export($class, $kind, $revision)`, its `$defs` registered as components. |
| D5 | **`ark4ne/openapi-writer` only writes OpenAPI; any conversion belongs to the generator** (decided 2026-11-17). A decoder from raw JSON Schema to writer `Schema` objects (the `fragment` of a custom type, a field's own `schema([...])` keywords) lives in `laravel-openapi-generator`, never in the writer. Not required now: such values are typed by a sample with an info diagnostic until it is asked for. |
| D6 | **The changelog of the document** is the base's `Changelog` between the document's revision and the head, appended to `info.description` (`describe()`); `Deprecation` / `Sunset` of the revision's `#[Meta]` are documented as response headers. |
| D7 | **`laravel-json-api` 3.0 (on the base) is a new integration `JsonApiV3`**, which needs the `ApiRevisions` integration; `JsonApiV2` stays for 2.0 without any revision code. Which one is available is decided by the installed version. |

## 2. Changes by layer

| Layer | New | Modified |
|---|---|---|
| Extraction | A document-aware source of ready-made schemas: `SchemaProvider::schema(class-string $class, SchemaUse $use, GenerationContext $context): ?Schema` (`$use`: response, request body, request query), asked before sampling and before rule parsing | `RequestExtractor` chain, the resource schema resolution |
| Integrations | `ApiRevisions\*` (integration, `Revisions`, `SchemaProvider`, changelog), `JsonApiV3\*` | `JsonApiV2\*` (revisions removed), `BuiltInIntegrations` |
| Laravel | — | `composer.json`: `suggest`, CI jobs |

## 3. Epics

### EA1 — Ready-made schemas

- **US 1.1 — JSON Schema decoder** (in the generator, D5; deferred until asked for): types, `enum`, `format`, `pattern`,
  bounds, `properties` / `required` / `additionalProperties`, `items`, nullable (`type: [T, 'null']`), `$ref`,
  `description`, `examples`, `deprecated`; an unknown keyword is kept as an extension or reported, never dropped
  silently.
- **US 1.2 — `SchemaProvider`**: the extension point of §2, registered by an integration; the first provider
  answering wins; its `$defs` go through `ComponentRegistry::register()`; a provider that throws is a warning and the
  sampled / inferred schema is used.

### EA2 — `ApiRevisions` integration

- **US 2.1 — The revision of a document**: `json_api.revision` (D2; a case of the application's `Revision` enum);
  invalid value or unknown setting: warning, the head documented.
- **US 2.2 — Requests in the revision**: `prepareRequest()` (D3); a `RevisionedRequest` is documented from its
  exported body and query schemas (names of the revision), never from `rules()`.
- **US 2.3 — Resources in the revision**: a `Revisioned` `JsonResource` is documented from its exported response
  schema; nested resources are components named as today.
- **US 2.4 — Changelog and lifecycle headers** (D6).
- **US 2.5 — Path mode**: routes declared with `Route::revisioned()` document the `{revision}` segment as the
  document's value, not as a parameter.
- **US 2.6 — Dependency and CI**: `suggest`; a CI job with the library, `#[Group('api-revisions')]`; PHPStan of the
  integration in that job only.

### EA3 — `JsonApiV3` integration

- **US 3.1** — `ResourceDefinitions` reads `Definitions::for($class, $revision)` with the revision of D2; the JSON:API
  envelope, relationships, `include` and `fields` rules as in `JsonApiV2`.
- **US 3.2** — Attribute and meta schemas: `laravel-json-api` 3.0 no longer has `AttributeDefinition` / `ValueType`;
  its attributes are the base's model. They are documented from the base's export of the resource (its `attributes`
  and `meta` objects), through the `SchemaProvider` and the decoder of EA1: `AttributeSchemas` is not ported.
- **US 3.3** — A resource absent from the revision: warning, as EI3 US 3.3.
- **US 3.4** — Remove from `JsonApiV2`: `Revisions`, the `json_api.revision` setting, `Revised` handling,
  `prepareRules()` use; the fixtures `JsonApiRevisions` move to `JsonApiV3` on the new enum contract.

Order: `EA1 → EA2`, and `EA3` after `laravel-json-api`'s epic EJ3. EI1 and EI2 are untouched.

## 4. Questions

| # | Question | Proposal |
|---|---|---|
| Q1 | The generator requires `illuminate/* ^13.0`; both libraries support `^12.46`. | Out of scope; note it in the README of the integration. |
| Q2 | Is `DocumentIntegration::prepareRules()` (added for `Revised`) still needed? | Keep it: it is generic and already delivered. |
| Q3 | A document without `json_api.revision` in an application that has revisions: the head, or the configured default? | The head: a document describes what the code declares. |
