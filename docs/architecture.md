# Architecture, concepts and patterns

This document sets the concepts and patterns of the generator. They follow those of
[`ark4ne/openapi-writer`](https://github.com/Ark4ne/openapi-writer) and adapt them to a Laravel package. Every
contribution follows them; a deviation is discussed and recorded here before it is coded.

**Guiding idea:** the generator **observes** the application (routes, controllers, requests, resources) and produces a
neutral **description** of it; **passes** complete it; `openapi-writer` **writes**. No layer does the work of another:
an extractor does not know the final document, the writing never reads the application.

## 1. Generation flow

```
Router ──► Discovery ──► RouteDefinition[] ──► Extraction ──► OperationDescription[] ──► Passes ──► DocumentBuilder ──► OpenApi ──► Output
              │                                   │    ▲                                  │              │            (writer)
              │                                   │    └── Sampling (instances, schemas)  │              │
              └──────────────── GenerationContext: document configuration, Diagnostics, Components ──────┘
```

1. **Discovery** reads the router and produces one `RouteDefinition` per kept route (URI, methods, action,
   middlewares, `where` constraints, model bindings). Nothing else reads the router.
2. **Extraction** turns each route into an `OperationDescription`: metadata (summary, tags, attributes), request
   (parameters, body, security), responses. Each aspect is handled by a registry of extractors.
3. **Sampling** gives the response extractors representative instances (factories, enums, fake objects) and infers
   schemas from values and types.
4. **Passes** work on all the operations at once: security from the middlewares, error responses, components and
   `$ref`, tags and groups, unique `operationId`.
5. **DocumentBuilder** translates the description into `openapi-writer` objects; **Output** writes the file (JSON or
   YAML, OpenAPI version from the configuration) and reports the validation.

## 2. Layers and dependency rule

```
Support ← Types ← Attributes ← Configuration ← Description ← Context ← Discovery ← Sampling ← Extraction ← Generation ← Output ← Integrations ← Laravel
                                       (Exceptions: usable by every layer)
```

| Layer | Folder | Content |
| --- | --- | --- |
| Support | `src/Support/` | Building blocks without internal dependency (`HttpStatus`, `MediaTypes`, strings and arrays) |
| Types | `src/Types/` | Type system: `Type` tree, `TypeResolver` (reflection + PHPDoc through `phpstan/phpdoc-parser`) |
| Attributes | `src/Attributes/` | Public PHP attributes set by the user (`#[Operation]`, `#[Response]`…) |
| Configuration | `src/Configuration/` | `Configuration`, `DocumentConfiguration`: the typed and validated configuration |
| Description | `src/Description/` | Immutable intermediate model (`OperationDescription`, `RequestDescription`…) |
| Context | `src/Context/` | `GenerationContext`, `Diagnostics`, `ComponentRegistry` |
| Discovery | `src/Discovery/` | `RouteCollector`, `RouteDefinition`, route filters |
| Sampling | `src/Sampling/` | `Sampler` (chain), `SamplingSession` (transactions), `SchemaInferrer` |
| Extraction | `src/Extraction/` | Extractor registries: `Operations/`, `Requests/`, `Rules/`, `Responses/` |
| Generation | `src/Generation/` | `Generator` (facade), `Pass` + `Passes/`, `DocumentBuilder` |
| Output | `src/Output/` | `DocumentWriter` (disk, JSON / YAML), validation report |
| Integrations | `src/Integrations/` | `JsonApi/` (`ark4ne/laravel-json-api`), `Sanctum/`…: shipped extensions |
| Laravel | `src/Laravel/` | `OpenApiServiceProvider`, Artisan commands, configuration loading |
| Exceptions | `src/Exceptions/` | Single exception hierarchy |

- A layer depends only on the layers to its left. Checked by `phpat` (`tests/Architecture/ArchitectureTest.php`).
- **Laravel:** every layer may depend on the `Illuminate\Contracts\*` **contracts** and on the classes it documents
  (`FormRequest`, `JsonResource`, `Route`…). Only the `Laravel` layer uses facades, global helpers (`config()`,
  `app()`, `request()`…) and the container. Checked by `phpat` and `spaze/phpstan-disallowed-calls`.
- **openapi-writer:** `Description`, `Extraction`, `Sampling` and `Generation` use `Schema`, `SecurityScheme`
  (a standalone leaf, stored as is in the components) and the writer's enums; only `Generation\DocumentBuilder` and
  `Output` build the other writer objects (`OpenApi`, `PathItem`, `Operation`…). The writer is the only way to write
  OpenAPI.
- `Integrations` is never a dependency of the core: an integration is registered by the configuration or by the
  service provider, and its third-party package stays in `suggest`. It implements `Generation\Integration`
  (resource formats, rule mappers, error bodies). The `Generator` keeps only the available integrations: an
  integration whose package is missing loads none of its classes. Only `Integrations\JsonApi` depends on
  `Ark4ne\JsonApi` (`phpat`), and the CI runs the suite without that package.

## 3. The description model

### 3.1 Immutable value objects

`src/Description/` holds the intermediate model: what the extraction understood of an operation, before any writing
decision. Patterns: **Immutable Value Object**, **Fluent Interface**, **Named Constructor**.

```php
final readonly class ParameterDescription extends Description
{
    protected function __construct(
        public string $name,
        public ParameterLocation $in,
        public Schema $schema,
        public bool $required = false,
        public ?string $description = null,
        public bool $deprecated = false,
    ) {}

    public static function path(string $name, Schema $schema): self
    {
        return new self($name, ParameterLocation::Path, $schema, required: true);
    }

    public function required(bool $required = true): static
    {
        return $this->copy(required: $required);
    }
}
```

Rules:

1. **`final readonly` classes**, extending the abstract base `Description`, which provides
   `copy(mixed ...$changes): static`. No other abstract class in `Description/`.
2. **Construction** through named constructors (`ParameterDescription::path()`, `ResponseDescription::ok()`); the
   constructor is `protected`. A description object is never built in the default value of a parameter of another
   class (the protected constructor is not reachable there): the named constructor passes it.
3. **Modification**: each method returns a copy (`copy()`); the current object never changes.
4. **Collections**: as in the writer, a setter that replaces (`parameters(...)`) and `add` + the exact field name
   that appends (`addParameters(...)`), both variadic and returning a copy. **Maps** keyed by a name (media types,
   components): a plural setter that replaces everything (`contents(array)`), a singular setter that sets or
   replaces one entry (`content('application/json', $schema)`) and `without*()` that removes it.
5. **Schemas**: a schema field is an `Ark4ne\OpenApiWriter\Objects\Schema`, never an array nor a custom class.
   Validation rules and inference build that `Schema` directly.
6. **No OpenAPI version in the description**: it describes the model of the latest version (`nullable()`, several
   types, `const`…); the writer converts on writing.

### 3.2 What an operation holds

| Object | Content |
| --- | --- |
| `OperationDescription` | Source route, HTTP method, `operationId`, summary, description, tags, `deprecated`, request, responses, security, extensions |
| `RequestDescription` | `ParameterDescription[]` (path, query, headers, cookies), `BodyDescription` (media types → `Schema`), presence of validation rules |
| `ResponseDescription` | Status code, description, headers, contents (media type → `Schema`), pagination, origin (inferred, attribute, pass) |
| `SecurityDescription` | Required security scheme and scopes |
| `DocumentDescription` | All the operations of a document, its tags, its security schemes; input and output of the passes |

### 3.3 `GenerationContext`

One `GenerationContext` per generated document, passed **explicitly** to each extractor, sampler and pass:

- the `DocumentConfiguration` of the current document (no `config()` call outside the `Laravel` layer);
- `Diagnostics`: warnings and errors, each with its route and step;
- `ComponentRegistry`: named schemas met (resources, enums), with name collision detection;
- the current locale (multilingual generation).

It is not a service container: dependencies come through constructors.

## 4. Extraction

### 4.1 Extractor registries

Patterns: **Chain of Responsibility** + **Registry**. Each aspect has its interface and its registry:

```php
interface ResponseExtractor
{
    public function supports(Type $type, OperationContext $operation): bool;

    /** @return list<ResponseDescription> */
    public function extract(Type $type, OperationContext $operation, GenerationContext $context): array;
}
```

| Registry | Input | Output | Examples |
| --- | --- | --- | --- |
| `OperationExtractors` | `OperationContext` | enriched `OperationDescription` | PHPDoc, `#[Operation]`, `#[Tag]`, naming strategies |
| `RequestExtractors` | `OperationContext` | enriched `RequestDescription` | Path parameters, `FormRequest`, `DescribableRequest`, attributes |
| `RuleMappers` | a validation rule | a modified `Schema` | `RequiredRule`, `EnumRule`, `PasswordRule`, `FileRule` |
| `ResponseExtractors` | return type of the action | `ResponseDescription[]` | `JsonResource`, `ResourceCollection`, `JsonResponse`, files, streams |

- `OperationContext` holds the route, the HTTP method, the OpenAPI path, the resolved action (reflection) and the
  request and return types of the action.
- **Two registry modes.** Operations and requests **enrich**: every extractor that answers `true` to `supports()`
  runs, by **ascending** priority, each one refining the description of the previous one (attributes, with a high
  priority, win over the PHPDoc). Responses and samplers **choose**: the first extractor that answers `true`, by
  **descending** priority, handles the input. Rules are indexed by name or by class.
- The priority is given by `priority(): int`, which returns a documented public `PRIORITY` constant, and not by the
  order of a configuration array.
- The user adds or replaces an extractor through the configuration (`extensions`) or a service provider; an
  integration extractor has a higher priority than the generic extractors it specialises.
- A family of resources written differently (JSON:API) is a **`ResourceFormat`** rather than an extractor. The format
  writes the component, the body and the collections of its resources, in its media type. `ResourceSchemas` asks the
  first format that accepts a resource, wherever it appears: returned, declared, nested or collected.
- **An extractor does not throw for a route it does not understand**: it adds a diagnostic and returns a partial
  description. A single failed route never blocks the generation (except in `--strict` mode).

### 4.2 Validation rules: one rule = one class

**Strategy** pattern, like the writer's rewriters: each Laravel rule behaviour has its `RuleMapper`, indexed by the
names (`'required'`, `'max'`) or the classes (`Illuminate\Validation\Rules\Enum`, `Password`, `File`) it handles;
rules with the same effect share a class (`required_with`, `required_without`…).

```php
interface RuleMapper
{
    /** @return list<string|class-string> */
    public function handles(): array;

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription;
}
```

- `RuleInvocation` holds the name, the parsed arguments and the rule object, if any; `FieldDescription` holds the
  `Schema` of the field, whether it is required, its conditions (`required_if`…) and its media types.
- Type-dependent rules (`max`, `size`, `between`) read the type already set by the other rules: the `RuleParser`
  applies the type rules first, then the constraints (explicit, tested order).
- Unknown rules produce an information-level diagnostic, never an error.

### 4.3 Type system

`Types\TypeResolver` reads the native types and the PHPDoc (`@return`, `@param`, `@var`, `@extends`, `@property`)
through `phpstan/phpdoc-parser`, and produces a `Type` tree (`NamedType`, `GenericType`, `ListType`,
`ArrayShapeType`, `UnionType`, `IntersectionType`, `NullableType`, `LiteralType`, `MixedType`). It resolves names
against the `use` statements of the file and the declared templates.

## 5. Sampling

Laravel responses are hard to describe statically: a `JsonResource` builds its array at runtime. The generator
therefore runs the resource on a representative instance, and infers the schema of the result.

| Pattern | Class | Role |
| --- | --- | --- |
| **Chain of Responsibility** | `Sampler` + `Samplers` | Find an instance: `#[ResourceFactory]` → factory `create()` → factory `make()` → enum case → `FakeObject` |
| **Unit of Work** | `SamplingSession` | Opens the transactions on the configured connections, guarantees the rollback, even on error |
| **Interpreter** | `SchemaInferrer` | PHP value + known `Type` → `Schema` (date formats, integers, `null`, lists, objects) |

- No data is ever kept: when no transaction is possible, only `make()` is used.
- The inferred schema is completed by the declared types (model casts, `@property` properties): a `null` value in
  the sample is not enough to make a field only `null`.

## 6. Passes

**Pipeline** pattern: each pass is a class that receives and returns a `DocumentDescription`.

```php
interface Pass
{
    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription;
}
```

- The order is explicit, in `Passes::default()`, and tested: security → error responses → tags and groups →
  `operationId` → components and `$ref` → cleanup.
- A pass handles **one** cross-cutting concern (`MiddlewareSecurityPass`, `ValidationErrorResponsePass`,
  `ComponentExtractionPass`…). Middlewares are mapped to security requirements by `MiddlewareMapper`s, used by
  `MiddlewareSecurityPass`.

## 7. Writing

- `DocumentBuilder` is the only **Mapper** between the description and `openapi-writer`. It makes no business
  decision: everything it writes is already in the description.
- The OpenAPI version and the strict or lenient mode come from the document configuration and are passed to the
  writer on writing (`toJson(Version::…)`). Version-specific features (tag groups as `Tag.parent` in 3.2,
  `x-tagGroups` before) are described once and converted by the writer or by a pass.
- `Output\DocumentWriter` writes on a Laravel disk (the `Filesystem` contract) and validates the document with the
  writer's `Validator`; violations join the `Diagnostics`.

## 8. Diagnostics and errors

- **Notification** pattern: `Diagnostics` collects everything (level, route, step, message) instead of stopping. The
  Artisan command shows them grouped by route; `--strict` makes the command fail on the first warning.
- Every exception implements `OpenApiGeneratorException`, extends the matching SPL exception and is created through
  named constructors (`InvalidConfigurationException::forKey('documents.v1.routes', '…')`).
- An exception is only for configuration errors and programming errors; whatever depends on the code of the
  documented application becomes a diagnostic.

## 9. Code conventions

| Topic | Rule |
| --- | --- |
| Classes | `final` by default; abstract only for base classes (`Description`) |
| Traits | Forbidden in `src/`: composition or an abstract base |
| Global state | No singleton, no mutable static property, no facade nor global helper outside `Laravel/` |
| Types | `declare(strict_types=1)`, every signature typed; `mixed` reserved for free values and documented |
| PHPDoc | Generics for collections (`list<ParameterDescription>`), no redundant PHPDoc |
| Naming | Interfaces without suffix (`Sampler`, `Pass`); implementations named after what they handle (`JsonResourceExtractor`, `EnumRuleMapper`, `MiddlewareSecurityPass`); no `Interface` or `Contract` suffix outside unions |
| Extractors / passes | `*Extractor`, `*RuleMapper`, `*Sampler`, `*Pass` suffixes, checked by `phpat` |
| Files | One class per file, PSR-4 under `Ark4ne\OpenApi\`, folder = layer |
| Language | Everything is in English, `docs/` included |

## 10. Test patterns

- **Unit** (`tests/Unit/`): one test per `RuleMapper` (provider rule → expected schema), per extractor, per sampler,
  per pass. Description objects are built by the Object Mother `tests/Support/Descriptions.php`.
- **Feature** (`tests/Feature/`, Orchestra Testbench): one scenario per feature, with its minimal application in
  `tests/Fixtures/` (routes, controllers, requests, resources, models, factories) and its expected documents
  `tests/Feature/<Scenario>/expected/<line>.json` (`3.0`, `3.1`, `3.2`).
- **Every generated document is validated** by the `openapi-writer` `Validator` (official schema + semantic rules)
  before the comparison: zero violation.
- `composer fixtures:update` rewrites the expected documents from the actual output; every change is reviewed in the
  diff.
- **Architecture** (`tests/Architecture/`): dependency rule, `final` classes, no trait, naming.
- Arrange / Act / Assert structure, test name as a sentence (`test_max_rule_on_string_sets_max_length`).
- Shared assertions live in a `TestCase` base class, not in traits.

## 11. Recipes

### Document a new validation rule

1. Create `src/Extraction/Rules/Mappers/<Rule>RuleMapper.php`, declare the rule in `handles()`.
2. Register it in `RuleMappers::default()`.
3. Add the cases to the unit test (one row per variant of arguments and type).

### Document a new response type

1. Create a `ResponseExtractor` in `src/Extraction/Responses/`, with its priority constant.
2. Register it in `ResponseExtractors::default()` (or in the provider of its integration).
3. Add a unit test and a feature scenario with its expected documents.

### Add a cross-cutting concern

1. Create a `Pass` in `src/Generation/Passes/` and insert it at its place in `Passes::default()`.
2. Test the pass alone (description in, description out) and its order in the chain.

## 12. Automatic safeguards

| Safeguard | What it checks |
| --- | --- |
| PHPStan max level + Larastan + strict rules | Types, dead code, comparisons, Laravel API |
| `phpat` | Dependency rule, `final` classes, no trait, naming |
| `spaze/phpstan-disallowed-calls` | Facades, global helpers and `dd()` / `dump()` outside `Laravel/` |
| PHP-CS-Fixer (PER-CS) | Style |
| Validation of the generated documents | Every output of the feature tests complies with the official schema and the writer's rules |
| Infection | Test quality (MSI ≥ 80%) |
