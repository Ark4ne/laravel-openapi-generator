# RFC — Integration settings per document

Status: **in progress** (EI1 and EI2 delivered; replaces the RFC *Document variants*) · Target: 1.2.0 · Blocked by:
the `ark4ne/laravel-json-api` 2.1.0 tag (epic EI3 only)

## 1. Goal

Let an integration change what it documents **per document**, so that the versions of an API are documented as
**one document configuration per version**. The core knows nothing about versions, revisions or headers: each document
carries a free block of settings per integration, and the integration reads its own.

```php
'documents' => [
    'v2026-09' => [
        'info' => ['title' => 'API', 'version' => '2026-09-01'],
        'routes' => ['include' => ['api/*'], 'exclude' => ['api/labels/*']],
        'integrations' => [
            'json_api' => ['revision' => App\Http\ApiRevision::Initial],
        ],
    ],
    'v2026-12' => [
        'info' => ['title' => 'API', 'version' => '2026-12-01'],
        'routes' => ['include' => ['api/*']],
        'integrations' => [
            'json_api' => ['revision' => App\Http\ApiRevision::Labels],
        ],
    ],
],
```

### Decisions

| # | Decision |
|---|---|
| D1 | **One document configuration per version.** No variant dimension in the core: a version is a document, with its own `info`, `routes`, `output` and integration settings. |
| D2 | **The endpoints of a version are chosen by configuration** (`routes.include` / `routes.exclude`), never by attributes in the core. |
| D3 | **The core never interprets the integration settings.** It checks their shape (an array per integration key) and hands them over; each integration validates its own. |
| D4 | **An integration does what its version needs to the requests** (headers, attributes, the package's revision): the core sets nothing on a request. |
| D5 | **A document without `integrations` is generated exactly as in 1.1.** The existing fixtures stay unchanged. |

### What this RFC replaces

The *Document variants* RFC (EV1 to EV3, unreleased, on the branch `feature/document-variants`) generated one document
per value of a variant dimension, set a variant header on the requests and documented it, and bounded operations with
`#[Since]` / `#[Until]`. All of this is removed before 1.2.0; what serves the new model is kept:

| Kept | Removed |
|---|---|
| `GenerationContext::sampleRequest()`, `prepareRequest()`, the request preparers and the `disallowed-calls` rule on request creation | `documents.<name>.variants`, `Configuration\Variant`, `Configuration\VariantsConfiguration`, `GenerationContext::$variant`, `variantName()` |
| `DocumentDescription::$notes`, joined to `info.description` | `{variant}` in `output.file` and `info.version`, `--variant` on `openapi:generate` and `openapi:inspect`, `Generator::variants()` |
| `UnusedComponentsPass` (now on every document), `SchemaWalker` and its fixes | `variants.header`, `VariantHeaderPass`, the header set by `prepareRequest()` |
| | `#[Since]`, `#[Until]`, `Description\VariantBounds`, `OperationDescription::$bounds`, `VariantBoundsPass` |
| | `Generation\VariantIntegration`, replaced by `Generation\DocumentIntegration` |

---

## 2. Changes by layer

| Layer | New | Modified |
|---|---|---|
| Configuration | — | `DocumentConfiguration` (+ `array<string, array<string, mixed>> $integrations`, `integration(string)`) |
| Context | — | `GenerationContext` (no variant; the preparers are those of the document integrations) |
| Generation | `DocumentIntegration` (optional interface) | `Generator` (one generation per document and locale; integration notes and passes), `Passes::default()` |
| Integrations | — | `JsonApiV2\*` (EI3) |
| Laravel | — | `config/openapi.php` (the `integrations` line), `GeneratorFactory` |

---

## 3. Definition of Done (every story)

1. Contract first (signatures, PHPDoc, neutral bodies), then red tests, then the implementation (`CLAUDE.md`).
2. Unit test per new class; Feature scenario with expected documents per OpenAPI version, validated by the writer.
3. **Non-regression**: the existing fixtures are unchanged (D5).
4. `composer ci` green (PHPStan max, phpat layers, PER-CS); `composer test:coverage` ≥ 90 %.
5. `CHANGELOG.md`, the skill `skills/laravel-openapi-generator/` and, for a new rule, `docs/architecture.md`.
6. No static state: the settings travel in the `DocumentConfiguration` of the `GenerationContext`.

---

## 4. Epics

### EI1 — Remove the variant dimension · M

Goal: the branch no longer has variants, headers or bounds; what the new model reuses stays and is tested.

Acceptance criteria:
- Everything in the *Removed* column of §1 is deleted with its tests, fixtures, configuration line, command options,
  skill and architecture sections and changelog entries.
- `Generator::all()` generates documents × locales, as in 1.1; `Generator::generate(Configuration,
  string|DocumentConfiguration, ?string $locale = null)`.
- `GenerationContext::prepareRequest()` only runs the request preparers; `GenerationContext::for()` loses its
  `$variant` parameter.
- `UnusedComponentsPass` runs on every document (checked: no existing fixture changes).
- The CHANGELOG `[Unreleased]` section no longer mentions variants; it lists what is kept (sample requests, unused
  components, the `SchemaWalker` fixes).

Tests: the existing suite green without the variant tests; `test_unused_components_are_removed_from_any_document`.

---

### EI2 — Integration settings and document integrations

Goal: an integration reads its settings in the document and changes the requests, the description and the passes of
that document.

#### US 2.1 — The `integrations` block · S

*As an application developer, I want to give each integration settings per document, so that each document documents
one version of my API.*

Acceptance criteria:
- `DocumentConfiguration::$integrations` (`array<string, array<string, mixed>>`, `[]` by default), read from
  `documents.<name>.integrations`; `integration(string $key): array<string, mixed>` (`[]` when absent).
- `fromArray()` rejects (`InvalidConfigurationException`) a non-array block, a block that is not an array, or a
  non-string key; values are kept as given (enum cases included: `config:cache` exports them).
- `withFormat()` keeps the block. Its line in `config/openapi.php`, commented out.
- Not to be confused with `extensions` (the `x-*` of the document).

Tests: `test_the_settings_of_an_integration_are_read_from_the_document`,
`test_an_absent_integration_has_empty_settings`, `test_integration_settings_must_be_arrays`.

#### US 2.2 — `DocumentIntegration` · M

*As an integration author, I want to prepare the requests, describe the document and add passes, so that my
integration documents the version its settings name.*

Acceptance criteria:
- `Generation\DocumentIntegration extends Integration` (optional), for the available integrations, in their order:
  - `key(): string`: the key of its settings (`json_api`);
  - `prepareRequest(Request $request, GenerationContext $context): void`: every sample request and every form request
    whose rules are read (the `prepareSampleRequest()` of `VariantIntegration`);
  - `describe(GenerationContext $context): ?string`: a markdown text appended to `info.description`, inside the
    sampling session (as `VariantIntegration::describe()`);
  - `passes(GenerationContext $context): list<Pass>`: passes for this document.
- Each method is called on every document; the integration reads `$context->document->integration($this->key())` and
  does nothing without settings.
- The integration passes run after the built-in passes, before the application's passes (which keep the last word),
  then `UnusedComponentsPass` and the cleanup.
- Each method that throws is a warning diagnostic; the other integrations still run.
- A key of `integrations` that no available document integration claims is a warning diagnostic (`configuration`):
  a typo, or a package not installed.

Tests: `test_an_integration_reads_its_settings`, `test_an_integration_prepares_the_requests_of_a_document`,
`test_an_integration_describes_a_document`, `test_the_passes_of_an_integration_run_before_those_of_the_application`,
`test_a_failing_integration_is_reported`, `test_unclaimed_integration_settings_are_reported`.

---

### EI3 — `ark4ne/laravel-json-api` 2.1 revisions

Goal: a document documents one revision of the package (its EV8). Everything is in `src/Integrations/JsonApiV2/`; it
uses only the stable contract of the package (`docs/architecture.md` §3 and §18): `Definitions::for/exists/lifetime`
with a revision, `Revision`, `RevisionOrder`, `Lifetime`, `Includes`, `Fields`, `JsonApi::useRevision()`,
`JsonApiContext::revisionOf()`, `Http\Rules\Revised`. Never anything marked `@internal`.

#### US 3.1 — The revision of a document · S

Acceptance criteria:
- `JsonApiV2Integration` implements `DocumentIntegration`, key `json_api`.
- Settings: `revision` (a case of an enum implementing `Ark4ne\JsonApi\Definition\Revision`, or its backed value);
  absent: the head, the 2.0 behaviour.
- An invalid `revision` (not a case, an unknown value, a package without revisions): warning diagnostic, the head
  documented.
- `JsonApiV2\Revisions::of(GenerationContext): ?Revision`.

Tests: `test_the_revision_of_a_document_is_read_from_its_settings`, `test_a_document_without_revision_documents_the_head`,
`test_an_invalid_revision_is_reported`.

#### US 3.2 — Resources and requests at the revision · M

Acceptance criteria:
- `ResourceDefinitions::of()` reads `Definitions::for($class, $revision)`, cached per context.
- `prepareRequest()` calls `JsonApi::useRevision($request, $revision)`: samples, `include` and `fields` render in the
  names of the revision.
- The rules of a form request are read at the revision: `Revised::resolve($rules, $revision)` (a no-op without
  `Revised` values), so a `JsonApiFormRequest`, a request using `UseRevisedRules` and a plain request are read alike;
  `include` and `fields` as before, with `revision:`.
- `IncludesRuleMapper` and `FieldsRuleMapper` read the revision of the rule, else that of the context.

Tests: Feature scenario `JsonApiRevisions` (fixture enum `Initial`, `Labels`, `UsersReplaceTeachers`; a renamed
attribute, a renamed relationship, a field `until()`, a field `since()`, a request with `Revised` rules; one document
per revision), expected documents per revision.

#### US 3.3 — Resources absent from a revision · S

Acceptance criteria:
- An operation whose request or response resource does not exist at the revision (`Definitions::lifetime()`): warning
  diagnostic naming the route, so that the application excludes it with `routes.exclude` (D2); the operation is kept.
- A relationship to a resource absent from the revision is never documented (`Definitions::for()` already gives the
  relationships of the revision).

Tests: `test_an_operation_on_a_resource_absent_from_the_revision_is_reported`.

#### US 3.4 — Changelog of a revision · S

Acceptance criteria:
- `describe()` lists the `description()` of the revisions newer than the document's, oldest first
  (`- 2026-12-01: Names become labels`); nothing for the newest revision and the head.

Tests: `test_a_revision_document_lists_the_newer_revisions`, `test_the_newest_revision_has_no_changelog`.

#### US 3.5 — Dependency and CI · S

Acceptance criteria:
- `suggest`: `^1.9 or ^2.0 (^2.1 for revisions)`; the json-api-v2 CI job installs `^2.1` and runs the scenario; the
  1.x and 2.0 jobs skip it (`#[Group('json-api-revisions')]`).
- README, skill and `CHANGELOG.md` document the setup: one document per revision, its `routes`, its `json_api` settings.

---

## 5. Order and questions

```
EI1 ── EI2 (US2.1 ── US2.2) ── EI3 (after the json-api 2.1.0 tag: US3.1 ── US3.2 ── US3.3 ── US3.4 ── US3.5)
```

EI1 and EI2 can be released alone (1.2.0) or with EI3.

| # | Question | Proposal |
|---|---|---|
| Q1 | The name of the block | `integrations`, keyed like the global `integrations` block (`json_api`): the global block holds the options of an integration, the document's its settings for that document. `extensions` already means the `x-*` of the document. |
| Q2 | Where the integration passes run | After the built-in passes, before the application's: an application pass sees what the integration did and may undo it. |
| Q3 | An operation on a resource absent from the revision | A warning, not a removal: the endpoints of a version are chosen by configuration (D2). |
| Q4 | `x-since`, `x-until`, `deprecated` per field of a request | Later (level 2): walk the unresolved rules, read `at($revision)` per document and the `lifetime()` of each `Revised` value (never `head()`, which may apply at no revision). |
| Q5 | Renames of request fields across revisions | Out of scope: the package does not translate request bodies yet (its RFC §8 Q2). |
