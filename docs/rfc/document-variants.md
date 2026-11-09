# RFC — Document variants

Status: **in progress** (EV1 and EV2 delivered) · Target: 1.2.0 · Blocked by: the `ark4ne/laravel-json-api` 2.1.0 tag (epic EV4 only)

## 1. Goal

Generate **one document per value of one dimension** of the application, the way documents are already generated once
per locale. The first use is the API revisions of `ark4ne/laravel-json-api` 2.1 (`Api-Version: 2026-12-01`, Stripe
style), but the core knows nothing about revisions: it carries an opaque **variant**, and each integration or extension
decides what the variant means for it.

```php
'documents' => [
    'v1' => [
        // …
        'info' => ['version' => '{variant}'],
        'variants' => [
            'values' => App\Http\ApiRevision::class, // an enum (its cases, in declaration order) or a list of strings
            'head' => 'latest',                      // an extra document without variant, named so; false for none
            'header' => 'Api-Version',               // documented on every operation, set on the sample requests
        ],
        'output' => ['file' => 'openapi-{document}-{variant}.json'],
    ],
],
```

`php artisan openapi:generate` then writes, for `v1`, one document per case of `ApiRevision` and one `latest` document.

### Decisions

| # | Decision |
|---|---|
| D1 | **One dimension per document.** No cartesian product (revision × tenant): two dimensions are two documents. Locales stay orthogonal: documents × variants × locales. |
| D2 | **The core never depends on a package.** A variant value is a `UnitEnum` or a `string`. An integration recognises its own values (`instanceof Ark4ne\JsonApi\Definition\Revision`) and ignores the others. |
| D3 | **Order is the declaration order** (enum cases, list items). It is the only comparison the core makes (bounds, EV3). The head, `null`, is newer than every variant. |
| D4 | **The head is opt-in** (`head: false` by default): with json-api the head renders as the newest case, so a head document is usually a duplicate. |
| D5 | **A document without `variants` is generated exactly as in 1.1.** Every story keeps the existing fixtures unchanged. |

### Out of scope

- Variants that change the routes themselves (a prefix per version): those are already separate documents with their
  own `routes.include`.
- Deprecation and sunset metadata: no source gives them yet (see §5, Q3).
- Request bodies per revision: `ark4ne/laravel-json-api` 2.1 does not validate them per revision either.

---

## 2. Changes by layer

| Layer | New | Modified |
|---|---|---|
| Configuration | `Variant`, `VariantsConfiguration` | `DocumentConfiguration` (+ `?VariantsConfiguration $variants`), `OutputConfiguration` (`{variant}`), `InfoConfiguration` (`{variant}` in `version`) |
| Description | `VariantBounds` | `OperationDescription` (+ `?VariantBounds $bounds`), `DocumentDescription` (+ `list<string> $notes`) |
| Attributes | `Since`, `Until` | — |
| Context | — | `GenerationContext` (+ `?Variant $variant`, request preparers, `sampleRequest()`) |
| Sampling | — | `SchemaInferrer` (sample request) |
| Extraction | — | `ResourceSchemas`, `ResponsableExtractor` (sample requests), operation extraction (bounds) |
| Generation | `VariantIntegration` (optional interface), `VariantHeaderPass`, `VariantBoundsPass`, `UnusedComponentsPass` | `Generator::generate()`, `Passes::default()`, `DocumentBuilder` (notes) |
| Output | — | `DocumentWriter` (`{variant}`) |
| Integrations | — | `JsonApiV2\*` (EV4) |
| Laravel | — | `GenerateCommand` (loop, `--variant`), `GeneratorFactory`, `config/openapi.php` |

---

## 3. Definition of Done (every story)

1. Contract first (signatures, PHPDoc, neutral bodies), then red tests, then the implementation (`CLAUDE.md`).
2. Unit test per new class; Feature scenario with expected documents per OpenAPI version, validated by the writer.
3. **Non-regression**: the existing fixtures are unchanged (D5).
4. `composer ci` green (PHPStan max, phpat layers, PER-CS); `composer test:coverage` ≥ 90 %.
5. `CHANGELOG.md`, the skill `skills/laravel-openapi-generator/` and, for a new rule, `docs/architecture.md`.
6. No static state: the variant travels in the `GenerationContext`.

---

## 4. Epics

### EV1 — The variant dimension

Goal: a document is generated once per variant, the variant is known to every extractor, sampler and pass.

#### US 1.1 — Variants configuration · S

*As an application developer, I want to declare the values of a dimension on a document, so that one document is
generated per value.*

Acceptance criteria:
- `Configuration\VariantsConfiguration` (`final readonly`): `values` (`list<Variant>`, ordered), `head` (`?string`,
  the name of the head document, `null` for none), `header` (`?string`); `named(string): ?Variant`,
  `names(): list<string>` (the variants, then the head).
- `DocumentConfiguration::withFormat()` keeps the variants.
- `fromArray()` accepts `values` as a `class-string<UnitEnum>` (its `cases()`) or a non-empty `list<string>`.
- The name of a variant: the value of a `BackedEnum` case, else the case name; a string is its own name.
- `InvalidConfigurationException` for: an unknown class or a class that is not an enum, an empty list, duplicate names,
  a `head` name equal to a variant name, a `header` that is not an HTTP token or is `Accept`, `Content-Type` or
  `Authorization` (header parameters OpenAPI ignores).
- `DocumentConfiguration::$variants` (`?VariantsConfiguration`, `null` by default) and its line in
  `config/openapi.php`, commented out.

Tests: `test_variants_of_an_enum_follow_the_order_of_its_cases`,
`test_a_backed_case_is_named_by_its_value`, `test_variants_with_duplicate_names_are_rejected`,
`test_a_head_named_as_a_variant_is_rejected`, `test_a_document_without_variants_has_no_variant_configuration`.

#### US 1.2 — The variant in the context · S

*As an extension author, I want to read the variant of the document being generated, so that my extractor or pass can
depend on it.*

Acceptance criteria:
- `Configuration\Variant` (`final readonly`, in Configuration because `VariantsConfiguration` builds it):
  `value` (`UnitEnum|string`), `name` (`string`), `position` (`int`, its index in the declaration order), `dimension`
  (the enum class or the configuration key of the list); `ofCase()`, `ofString()`; `isAtMost()`, `isAtLeast()`,
  `is()`. Comparing variants of two dimensions is a `LogicException`.
- `GenerationContext::$variant` (`?Variant`, last constructor parameter, `null` = head or no variants);
  `GenerationContext::for(…, ?Variant $variant = null)`.
- `Generator::generate(Configuration, string|DocumentConfiguration, ?string $locale = null, ?Variant $variant = null)`.
- `GenerationContext::variantName(): ?string`: the name of the variant, of the head, or `null` without variants (file
  names, `info.version`, labels).
- `VariantsConfiguration::nameOf(?Variant): ?string` and `DocumentConfiguration::generation(string $name): ?Variant`
  (the `--variant` of `openapi:generate` and `openapi:inspect`; `InvalidConfigurationException` for an unknown name).
- Variant and head names match `[A-Za-z0-9][A-Za-z0-9._-]*` (they are written in file names); an enum without cases
  is rejected; `{variant}` in `info.version` of a document without variants is rejected.
- `openapi:inspect --variant=`, the first generation of the document by default.
- `Generator::variants(DocumentConfiguration): list<?Variant>`: the generations of a document (its variants, then
  `null` for its head; `[null]` without variants).

Tests: `test_variants_are_compared_by_their_declaration_order`,
`test_comparing_variants_of_two_configurations_throws`, `test_the_context_carries_the_variant_of_the_generation`.

#### US 1.3 — One generation per variant · M

*As an application developer, I want `openapi:generate` to write every variant, so that one command publishes every
revision of my API.*

Acceptance criteria:
- `GenerateCommand` generates documents × (variants + head) × locales; a document without `variants` once, as today.
- `--variant=<name>` restricts to one variant (`--variant=latest` for the head); an unknown name fails with the list of
  the known names. `--stdout` still requires exactly one generation.
- The label of a generation names the variant: `v1 [2026-12-01] (fr)`.
- `DocumentWriter`: `{variant}` in `output.file` is replaced by the variant name (the head name for the head). Without
  `{variant}` in the file name, the name is inserted before the extension, as for `{locale}` (`DocumentWriter.php:88`).
- `info.version` accepts `{variant}`.

Tests: `test_a_document_is_generated_once_per_variant_and_for_the_head`,
`test_the_variant_option_generates_one_variant`, `test_an_unknown_variant_option_fails`,
`test_the_variant_is_inserted_in_a_file_name_without_placeholder`, `test_the_info_version_names_the_variant`.

---

### EV2 — What the core documents of a variant

Goal: the generic effects of a variant, useful without any integration.

#### US 2.1 — The variant header · S

*As an API client, I want to see which header selects the revision, so that I send the right one.*

Acceptance criteria:
- `Generation\Passes\VariantHeaderPass`: with `variants.header` set, every operation gets a header parameter of that
  name.
  - In a variant document: `schema: {type: string, const: <name>}` (`enum: [<name>]` in 3.0), `example: <name>`.
  - In the head document: `schema: {type: string, enum: [<every variant name>]}` (not the head name).
  - Never required (Q2); webhooks and callbacks get none (the application sends them).
  - Built-in, after `PathParametersPass`, before the application's passes.
- A header parameter of the same name (case-insensitive) already on the operation (`#[Header]`, a middleware mapper) is
  kept as declared.

Tests: `test_every_operation_of_a_variant_documents_its_header`,
`test_the_head_document_lists_every_variant_in_the_header`, `test_a_declared_header_is_not_replaced`.

#### US 2.2 — Sample requests carry the variant · M

*As an integration author, I want every sample request to carry the variant, so that the application renders the
samples as a client of that variant would see them.*

Acceptance criteria:
- `GenerationContext::sampleRequest(): Request`: a new `GET /` request, with the variant header when it is configured
  and a variant is generated (not for the head), then prepared by every request preparer of the context, in order.
  It lives in the context, not in Sampling: every call site already receives the context, and Sampling cannot depend
  on the integrations (Generation).
- `GenerationContext::$requestPreparers` (`list<Closure(Request, GenerationContext): void>`, last constructor
  parameter, also on `for()`): the `Generator` gives it the `prepareSampleRequest()` of the available
  `VariantIntegration`s.
- It replaces every `Request::create('/')` of `src/` (`SchemaInferrer.php:145`, `ResourceSchemas.php:249,286,317`,
  `ResponsableExtractor.php:67`, `JsonApiV2/JsonApiResourceFormat.php:354`, the 1.x integration). A
  `disallowed-calls` rule forbids `Request::create()` outside `GenerationContext`.
- `Generation\VariantIntegration extends Integration` (optional): `prepareSampleRequest(Request $request,
  GenerationContext $context): void`, called for every sample request, the head's included.
- `GenerationContext::prepareRequest(Request): Request` does the same on a request the generator builds: the form
  request whose rules are read (`RequestFactory::make(…, GenerationContext)`) carries the variant too, so that rules
  that depend on it (US 4.2) need nothing more. `RequestFactory` is the only other class allowed `Request::create()`
  (through `FormRequest::create()`); `new Request()`, `createFrom()`, `createFromBase()` and `createFromGlobals()`
  are forbidden everywhere in `src/`.
- A preparer that throws is a warning diagnostic (`sampling`); the next preparers still run.
- Known limit: the request of the container is not replaced. Application code reading `request()`, or a `Request`
  injected into `rules()` by the container, sees no variant. Binding the sample request in the container would need
  the Laravel layer and an extension point; to reconsider on a real case.

Tests: `test_a_sample_request_carries_the_variant_header`,
`test_an_integration_prepares_the_sample_requests_of_a_variant`, `test_a_sample_request_of_the_head_has_no_header`,
`test_the_rules_of_a_form_request_are_read_on_a_request_of_the_variant`.

#### US 2.3 — The description of a variant · S

*As an API client, I want the document of a revision to say what changed, so that I know what upgrading brings.*

Acceptance criteria:
- `VariantIntegration::describe(GenerationContext $context): ?string`: a markdown text appended to `info.description`
  (after the configured description, separated by a blank line), in the order of the integrations.
- The texts travel in `DocumentDescription::$notes` (`notes()`, `addNotes()`), added by the `Generator` before the
  passes; `DocumentBuilder` joins them to the configured description.
- No integration describing: `info.description` as configured.
- `describe()` runs inside the sampling session (what it samples is rolled back); one that throws is a warning
  diagnostic (`pass`), the other descriptions kept.

Tests: `test_an_integration_describes_the_variant_in_the_document`,
`test_a_document_without_description_from_integrations_keeps_its_own`.

---

### EV3 — Bounds of an operation

Goal: an operation exists from one variant and/or up to one variant; the same mechanism serves the integrations.

#### US 3.1 — `#[Since]` and `#[Until]` · M

*As an application developer, I want to declare the first and last variants of an action, so that each document lists
only the operations of its variant.*

Acceptance criteria:
- `Attributes\Since(UnitEnum|string $variant)` and `Attributes\Until(UnitEnum|string $variant)`, on a controller class
  or an action method (the method wins); both **inclusive**, as in `ark4ne/laravel-json-api`.
- `Description\VariantBounds` (`final readonly`): `since`, `until` (names, `?string`), `covers(?Variant)`;
  `OperationDescription::$bounds` (`?VariantBounds`) with its replacing setter.
- `Generation\Passes\VariantBoundsPass`: removes the operations whose bounds do not cover the variant. The head is
  covered by an operation without `until`.
- A bound naming no variant of the document: error diagnostic, the operation kept.
- Without `variants` on the document, the bounds are ignored.

Tests: `test_an_operation_since_a_variant_is_absent_from_older_documents`,
`test_an_operation_until_a_variant_is_absent_from_newer_documents_and_the_head`,
`test_bounds_are_inclusive`, `test_a_method_bound_overrides_the_controller_bound`,
`test_an_unknown_bound_is_reported`, `test_bounds_are_ignored_without_variants`.

#### US 3.2 — Unused components · S

*As an API client, I want a document without the schemas of the operations it does not have.*

Acceptance criteria:
- `Generation\Passes\UnusedComponentsPass`, after `ComponentExtractionPass`, in documents with `variants` only (D5):
  removes the component schemas no longer referenced, transitively.

Tests: `test_a_component_referenced_only_by_a_removed_operation_is_removed`,
`test_a_component_referenced_by_another_component_is_kept`.

---

### EV4 — `ark4ne/laravel-json-api` 2.1 revisions

Goal: the json-api integration reads the variant as a revision (EV8 of the package). Everything is in
`src/Integrations/JsonApiV2/`; it uses only the stable contract of the package (its `docs/architecture.md` §3 and §18):
`Definitions::for/exists/lifetime` with a revision, `Revision`, `RevisionOrder`, `Includes::revision()`,
`Fields::revision()`, `JsonApi::useRevision()`. Never anything marked `@internal`.

#### US 4.1 — The revision of a variant · S

Acceptance criteria:
- `JsonApiV2\Revisions::of(GenerationContext): ?Revision`: the variant value when it implements
  `Ark4ne\JsonApi\Definition\Revision` and the installed package supports revisions (`interface_exists`), else `null`
  (the head, the 2.0 behaviour).
- Variant values that are not revisions while the package supports them: one info diagnostic per document.
- `JsonApiV2Integration` implements `VariantIntegration`.

Tests: `test_a_revision_variant_is_read_as_a_revision`, `test_a_string_variant_documents_the_head`.

#### US 4.2 — Definitions and samples at the revision · M

Acceptance criteria:
- `ResourceDefinitions::of()` reads `Definitions::for($class, $revision)`, cached per context (the context already
  holds one variant).
- `prepareSampleRequest()` calls `JsonApi::useRevision($request, $revision)`: samples, `include` and `fields` render in
  the names of the revision.
- `IncludesRuleMapper` and `FieldsRuleMapper` read the revision of the rule (`revision()`), else that of the context:
  the `include` and `fields[type]` parameters list the names of the revision. A form request's rules are read with a
  sample request carrying the revision (US 2.2).

Tests: Feature scenario `JsonApiRevisions` (fixture enum `Initial`, `Labels`, `UsersReplaceTeachers`, a renamed
attribute, a renamed relationship to an older resource, a field `until()`, a field `since()`), expected documents per
revision; Unit `test_an_include_parameter_lists_the_paths_of_the_revision`.

#### US 4.3 — Resources absent from a revision · S

Acceptance criteria:
- An operation whose response or request resource has a `Definitions::lifetime($class)` sets its `VariantBounds` from
  `since`/`until`; `VariantBoundsPass` (US 3.1) removes it.
- A relationship to a resource absent from the revision is never documented (`Definitions::for()` already gives the
  relationships of the revision); a resource that cannot be read at the revision: warning diagnostic, no exception.

Tests: `test_an_operation_on_a_resource_until_a_revision_is_absent_from_newer_documents`,
`test_the_component_of_a_resource_absent_from_a_revision_is_not_written`.

#### US 4.4 — Changelog of a revision · S

Acceptance criteria:
- `describe()` lists the `description()` of the revisions newer than the document's, oldest first
  (`- 2026-12-01: Names become labels`); nothing for the newest revision and the head.

Tests: `test_a_revision_document_lists_the_newer_revisions`, `test_the_newest_revision_has_no_changelog`.

#### US 4.5 — Dependency and CI · S

Acceptance criteria:
- `suggest`: `^1.9 or ^2.0 (^2.1 for revisions)`; the json-api-v2 CI job installs `^2.1` and runs the scenario; the
  1.x and 2.0 jobs skip it (`#[Group('json-api-revisions')]`).
- README, skill and `CHANGELOG.md` document the setup: the enum, `variants.header` equal to the header read by the
  application's middleware.

---

## 5. Order and questions

```
EV1 ── US1.1 ── US1.2 ── US1.3
                  │
EV2 ──────────────┼── US2.1 ── US2.2 ── US2.3
                  │
EV3 ──────────────┴── US3.1 ── US3.2
                                  │
EV4 (after the json-api 2.1.0 tag) US4.1 ── US4.2 ── US4.3 ── US4.4 ── US4.5
```

EV1 to EV3 can be released alone (1.2.0) or with EV4.

| # | Question | Proposal |
|---|---|---|
| Q1 | The name of the head document | Configured (`head: 'latest'`), opt-in (D4). |
| Q2 | `required: true` for the header in a variant document | No: a client without the header gets the application's default revision, which the generator cannot know. |
| Q3 | `deprecated: true` on a field or an operation with `until()` | Later: the package gives no deprecation meaning to `until()`. |
| Q4 | A changelog diff between two revisions (renamed, added, removed fields) | Later: the package's `jsonapi:revisions --format=json` (its US 6.3) is not implemented. |
