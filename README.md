Laravel OpenAPI Generator
=========================

[![CI](https://github.com/Ark4ne/laravel-openapi-generator/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/Ark4ne/laravel-openapi-generator/actions/workflows/ci.yml)
[![Coverage](https://codecov.io/gh/Ark4ne/laravel-openapi-generator/graph/badge.svg)](https://codecov.io/gh/Ark4ne/laravel-openapi-generator)
[![Latest version](https://img.shields.io/packagist/v/ark4ne/laravel-openapi-generator)](https://packagist.org/packages/ark4ne/laravel-openapi-generator)
[![PHP version](https://img.shields.io/packagist/dependency-v/ark4ne/laravel-openapi-generator/php)](https://packagist.org/packages/ark4ne/laravel-openapi-generator)
[![License](https://img.shields.io/packagist/l/ark4ne/laravel-openapi-generator)](LICENSE)

Generates OpenAPI 3.0, 3.1 and 3.2 documents from a Laravel application: routes, controllers, form requests,
validation rules and API resources. It reads what the application already declares, runs the resources on sampled
models, and writes each document in the OpenAPI version you choose with
[`ark4ne/openapi-writer`](https://github.com/Ark4ne/openapi-writer).

- **No annotations to maintain**: the return types, the PHPDoc, the form requests and the middlewares are the
  documentation; PHP attributes complete what cannot be inferred.
- **Every OpenAPI version**: 3.0.0 → 3.0.4, 3.1.0 → 3.1.2, 3.2.0, chosen per document (3.1.2 by default).
- **Errors documented**: 401, 403, 404, 419, 422 and 429 from the middlewares, the bindings and the validation;
  error responses from the `@throws` of the actions.
- **Extensible**: priority-ordered extractors, rule mappers, samplers and passes, built through the container.
- **Debuggable**: diagnostics grouped by route, `openapi:inspect` to see what the generator understood of a route.

## Requirements

- PHP 8.3+
- Laravel 13
- `ark4ne/openapi-writer` 1.0, installed with the package

Optional:

- `opis/json-schema` to validate the generated documents against the official OpenAPI schemas (recommended)
- `symfony/yaml` to write YAML
- `ark4ne/laravel-json-api` ^1.9 to document JSON:API resources

Upgrading from 0.6? See [UPGRADE.md](UPGRADE.md).

## Installation

```bash
composer require ark4ne/laravel-openapi-generator
php artisan vendor:publish --tag=openapi-config
php artisan openapi:generate
```

The service provider is discovered automatically. The document is written on the default disk, in
`openapi/openapi-v1.json` with the published configuration. `php artisan about` shows the version, the documents
and whether the JSON:API integration is enabled.

## How it documents an endpoint

```php
use App\Http\Requests\StoreBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Ark4ne\OpenApi\Attributes\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

final class BookController
{
    /**
     * List the books.
     *
     * Sorted by title.
     *
     * @return AnonymousResourceCollection<BookResource>
     */
    #[Response(200, resource: BookResource::class, paginator: LengthAwarePaginator::class)]
    public function index(): AnonymousResourceCollection
    {
        return BookResource::collection(Book::query()->orderBy('title')->paginate());
    }

    /**
     * Add a book.
     *
     * @throws \App\Exceptions\DuplicateIsbnException
     */
    public function store(StoreBookRequest $request): BookResource
    {
        return new BookResource(Book::query()->create($request->validated()));
    }
}
```

| What the generator reads | What it writes |
| --- | --- |
| `Route::post('api/books', …)->middleware('auth:sanctum')` | the path, the operation id (`books.store`), the Sanctum security scheme and a 401 |
| The PHPDoc | the summary (first line) and the description (the rest); `@deprecated` |
| `StoreBookRequest::rules()` | the request body (the query for `GET`, `HEAD`, `DELETE`), with every constraint, and a 422 |
| The return type `BookResource` | a 201 (`POST`) with the `Book` component, built by running the resource on a sampled `Book` |
| `@throws DuplicateIsbnException` | the response the exception declares with `#[Response(409)]` |
| A bound model (`Book $book`) | the type of the path parameter and a 404 |
| `#[Response]` | what the return type cannot tell: here the paginator, giving `data`, `links` and `meta` |

Resources are sampled from the models: with a factory, inside a transaction rolled back at the end of the
generation; without, from fake values typed by the casts, the `@property` tags and the columns of the table. A key
of `toArray()` computed with `when()` or `whenLoaded()` is optional, the others are required.

## Attributes

All in `Ark4ne\OpenApi\Attributes`; on the controller class they apply to every action, the action overrides them.

| Attribute | Use |
| --- | --- |
| `#[Operation(summary:, description:, operationId:, tags:, deprecated:, externalDocs:)]` | Replaces the metadata given by the PHPDoc and the strategies |
| `#[Tag('books', description:, parent:, summary:, kind:)]` | Adds and describes a tag; `parent` gives the tag groups |
| `#[Response(201, resource:, type:, headers:, paginator:, mediaType:, description:, example:)]` | Declares the response of a status (repeatable), on an action or an exception class; a declared 2xx replaces the successes inferred for other statuses |
| `#[RequestBody(type:, resource:, mediaType:, description:, required:)]` | Replaces or completes the body |
| `#[QueryParameter]`, `#[Header]`, `#[Cookie]`, `#[PathParameter]` | Adds or completes a parameter (`name`, `type`, `description`, `required`, `example`) |
| `#[Security('sanctum', scopes: [])]` | Alternative security requirements, replacing those of the middlewares |
| `#[Exclude]` | Leaves a controller, an action or a closure route out |
| `#[Webhook('order')]`, `#[Callback('paid', url:, webhook:)]` | Requests the application sends (see [Webhooks](#webhooks-and-callbacks)) |
| `#[ResourceFactory(factory:, parameters:, method:)]` | Builds the sample of a resource that does not receive a model |
| `#[SchemaName('Book')]` | Name of the component of a resource or an enum |

Types are PHPDoc types: `int`, `positive-int`, `list<string>`, `'asc'|'desc'`, `array{id: int, tags?: list<string>}`,
a class. Every parameter is described in the [agent skill reference](skills/laravel-openapi-generator/reference.md).

## Requests

The rules of a form request give the fields: `required`, `nullable`, `sometimes`, `string`, `integer`, `numeric`,
`boolean`, `array`, `in:`, `Rule::in()`, `Rule::enum()`, `min:`, `max:`, `size:`, `between:`, `regex:`, `email`,
`url`, `uuid`, `date`, `date_format:`, `file`, `image`, `mimes:`, `File`, `Password`, `required_if:` and the other
conditions (written in the description)… Unknown rules are reported as infos.

A request completes or replaces its rules with `DescribesRequest`; a rule object of the application describes
itself with `DescribesRule`:

```php
use Ark4ne\OpenApi\Extraction\Requests\DescribesRequest;
use Ark4ne\OpenApi\Extraction\Requests\RequestDescriber;

final class SearchRequest extends FormRequest implements DescribesRequest
{
    public function rules(): array
    {
        return ['q' => 'required|string|min:2'];
    }

    public function describe(RequestDescriber $request): RequestDescriber
    {
        return $request->header('X-Tenant', 'required|uuid', 'Tenant of the request');
    }
}
```

## Errors

| Response | Comes from |
| --- | --- |
| 401 | `auth`, `auth:<guard>`, `auth.basic` |
| 403 | `can:`, `signed`, a form request redefining `authorize()`, `@throws AuthorizationException` |
| 404 | a bound model or backed enum in the path, `@throws ModelNotFoundException` |
| 419 | the CSRF middleware |
| 422 | a form request, `@throws ValidationException` |
| 429 | `throttle` (with the rate limit headers) |
| any | `@throws` of the action: `#[Response]` on the exception class, `exceptions.map`, or Laravel and Symfony's rendering |

They are shared responses (`components.responses`) with the `Error` and `ValidationError` bodies of Laravel.

## Webhooks and callbacks

A webhook is a request the application **sends**. Describe it as a route whose action receives it (its form request
is the payload) and mark it `#[Webhook]`: it is written under `webhooks` (3.1+, or `x-webhooks` before with the
`redoc` vendor extensions), never under `paths`. `#[Callback]` documents a request sent back to a URL given by the
client, reusing a webhook or describing its own payload.

These routes exist only to be documented. Like any route, they must match `routes.include` (`api/*` by default),
and a middleware must keep them from answering. Do not register them conditionally (`app()->runningInConsole()`):
`php artisan route:cache` runs in the console too, so they would end up in the route cache and be served.

```php
// routes/api.php
Route::middleware(DocumentedOnly::class)->group(function () {
    Route::post('webhooks/orders/paid', [OrderWebhooks::class, 'paid']);   // documented, never served
});

final class DocumentedOnly
{
    public function handle(Request $request, Closure $next): never
    {
        abort(404);
    }
}
```

## JSON:API

With [`ark4ne/laravel-json-api`](https://github.com/Ark4ne/laravel-json-api) installed, its resources are written in
`application/vnd.api+json` as resource objects (`id`, `type`, `attributes`, `relationships`, `links`, `meta`) with
their `included` resources, its descriptors (`$this->string()`, `$this->date()`, `$this->struct()`…) give the types of
the attributes, and its `Includes` and `Fields` rules give the `include` and `fields[type]` parameters. Set
`integrations.json_api.errors` to `true` if the application renders its errors as JSON:API error documents.

## Extending

Every registry takes the classes listed in `extensions` (or tagged in the container by a package), built through the
container:

| Interface | For |
| --- | --- |
| `Extraction\Rules\RuleMapper` | a validation rule the package does not know (`phone`) |
| `Extraction\Responses\ResponseExtractor` | a return type the package does not know |
| `Extraction\Requests\RequestExtractor` | another source of parameters or bodies |
| `Extraction\Operations\OperationExtractor` | other metadata of the operations |
| `Sampling\Sampler` | building the samples of a class |
| `Generation\Pass` | a change over the whole document |
| `Generation\MiddlewareMapper` | what a middleware means (`middlewares` key) |

```php
final class PhoneRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['phone'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        return $field->schema($field->schema->type('string')->pattern('^\+[1-9][0-9]{6,14}$'));
    }
}

// config/openapi.php
'extensions' => ['rule_mappers' => [App\Docs\PhoneRuleMapper::class]],
```

## Commands

| Command | What it does |
| --- | --- |
| `openapi:generate` | Generates every document of the configuration, in each language |
| `openapi:inspect {route}` | Shows what the generator understood of a route (name, URI, or method and URI) and its diagnostics |

`openapi:generate` options:

| Option | Effect |
| --- | --- |
| `--document=*` | Documents to generate (all by default) |
| `--strict` | Fails on the first warning; the document is not written |
| `--dry-run` | Generates and validates, without writing |
| `--format=` | `json` or `yaml`, instead of the configured format (the file takes the extension of the format) |
| `--stdout` | Prints the document instead of writing it (one document; the diagnostics go to the error output) |

The diagnostics are grouped by route: the warnings by default, the infos with `-v`; `-q` keeps the errors only.

Exit codes: `0` every document is written, `1` a document has an error (or a warning with `--strict`) and is not
written, `2` the configuration or the options are invalid.

## Configuration

Every key of `config/openapi.php` is read when a command runs; an invalid key or value fails the command with the key,
the expected value and the received one (`openapi.documents.v1.openapi: expected one of 3.0.0, …, got "4.0"`).

| Key | Default | Meaning |
| --- | --- | --- |
| `documents.<name>` | `v1` | One OpenAPI document; the name is used by `--document` and `{document}` |
| `documents.<name>.openapi` | `3.1.2` | Version written: `3.0.0` → `3.0.4`, `3.1.0` → `3.1.2`, `3.2.0` |
| `documents.<name>.strict` | `true` | Rejects what the version cannot write (`false`: dropped with a warning) |
| `documents.<name>.vendor_extensions` | `none` | `redoc`: `x-webhooks` before 3.1, `x-tagGroups` before 3.2 |
| `documents.<name>.info` | | `title`, `version`, `summary`, `description`, `terms_of_service`, `contact`, `license` |
| `documents.<name>.self` | `null` | Public URI of the document (`$self`, 3.2) |
| `documents.<name>.external_docs` | `null` | `url` and `description` |
| `documents.<name>.servers` | `[]` | Servers of the document; the global `servers` when empty |
| `documents.<name>.routes` | `api/*` | `include`, `exclude` (masks on `uri`, `name`, `domain`, `middleware`), `ignored_methods`, `internal`, `order` (`registration` or `path`) |
| `documents.<name>.strategies` | | `summary`, `tags`, `groups`, `operation_id`, `query`: a built-in strategy (`by`) or a class implementing its interface, built through the container |
| `documents.<name>.security_schemes` | `[]` | Security Scheme Objects completing or replacing those of the middlewares |
| `documents.<name>.security` | `[]` | Schemes required by default on every operation |
| `documents.<name>.use_references` | `true` | Resources and enums as components referenced by `$ref` |
| `documents.<name>.output` | | Overrides of the global `output` |
| `documents.<name>.extensions` | `[]` | `x-*` extensions of the document |
| `servers` | `APP_URL` | Default servers: `url`, `description`, `name` (3.2), `variables` |
| `output` | | `disk` (`null`: the default disk), `directory`, `file` (`{document}`, `{locale}`), `format` (`json` or `yaml`) |
| `sampling` | | `transactions`, `connections` (drivers), `collection_size`, `seed`, `examples` |
| `date_formats` | | PHP date format → OpenAPI format of the validation rules |
| `middlewares` | `[]` | Middleware (name or class) → classes implementing `MiddlewareMapper` |
| `extensions` | `[]` | `operation_extractors`, `request_extractors`, `rule_mappers`, `response_extractors`, `samplers`, `passes`: classes resolved through the container |
| `integrations.json_api.errors` | `false` | Error responses as JSON:API error documents (with `ark4ne/laravel-json-api`) |
| `exceptions` | | `read_throws` (the `@throws` of the actions), `map` (exception class → status) |
| `languages` | `[]` | One document per locale; texts may be translation keys |

Third-party packages add extensions with container tags: `openapi.operation-extractors`,
`openapi.request-extractors`, `openapi.rule-mappers`, `openapi.response-extractors`, `openapi.samplers`,
`openapi.passes`.

## Agent skill

The package ships a skill for coding agents in [`skills/laravel-openapi-generator/`](skills/laravel-openapi-generator/SKILL.md):
the rules, the reference of every attribute, configuration key and extension point, and tested recipes. Point your
agent to `vendor/ark4ne/laravel-openapi-generator/skills/laravel-openapi-generator/`.

A second skill, [`skills/laravel-openapi-generator-upgrade/`](skills/laravel-openapi-generator-upgrade/SKILL.md),
migrates an application from 0.6: procedure, mapping of every 0.6 key, tag, attribute and class, and tested ports.

## FAQ

**A route is missing from the document.** It is not matched by `routes.include` (`api/*` by default), or it is
excluded (`routes.exclude`, `#[Exclude]`, a redirect or view route, an internal route of Laravel or of a package).
`php artisan openapi:inspect <route>` tells whether it is in the document.

**A response has no schema.** The action has no return type, or returns an untyped `JsonResponse`: type its return
(`@return`) or declare `#[Response(type: 'array{…}')]`. `openapi:generate -v` lists these cases.

**A resource is empty, or a property is missing.** `toArray()` failed on the sampled model: the warning says why.
Give the model a factory, `@property` tags or casts, or the resource a `#[ResourceFactory]`.

**Does the generation touch the database?** Factories run inside a transaction rolled back at the end, opened on the
default connection only, when its driver is in `sampling.connections`; models of another connection, or of a driver
not listed, are only made, never saved. Set
`sampling.transactions` to `false` to never write.

**How do I keep the documentation from degrading?** Run `php artisan openapi:generate --strict --dry-run` in CI: it
fails on the first warning.

**Can I serve the document (Swagger UI, Redoc, Scalar)?** Not from this package: point a dedicated package or a static
viewer to the generated file.

**Which version should I write?** 3.1.2 unless a consumer needs another: 3.0.x for older tools and code generators,
3.2.0 for its new features (tag hierarchy, `query` method, `$self`). One configuration can write several documents.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

See [LICENSE](LICENSE).
