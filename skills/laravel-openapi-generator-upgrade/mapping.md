# Mapping: 0.6 → 1.0

Everything 0.6 exposed to an application, and what replaces it in 1.0. Ported code: [examples.md](examples.md). How
1.0 works and what each attribute and key does: the `laravel-openapi-generator` skill
(`vendor/ark4ne/laravel-openapi-generator/skills/laravel-openapi-generator/`).

## Requirements

| | 0.6 | 1.0 |
| --- | --- | --- |
| PHP | 8.1+ | 8.3+ |
| Laravel | 10 → 13 | 13 |
| OpenAPI written | 3.0.2 only | 3.0.0 → 3.0.4, 3.1.0 → 3.1.2, 3.2.0, per document (3.1.2 by default) |
| OpenAPI objects | `goldspecdigital/oooas` | `ark4ne/openapi-writer` |
| PHPDoc reader | `phpdocumentor/reflection-docblock` | `phpstan/phpdoc-parser` (generics, array shapes, conditional types) |

Upgrade PHP and Laravel first. Remove `goldspecdigital/oooas` from `composer.json` if the application required it
only for the generator; code that imports `GoldSpecDigital\ObjectOrientedOAS` must be ported (writer objects:
`Ark4ne\OpenApiWriter\Objects\*`).

## Configuration

1.0 rejects any unknown top-level key (`versions`, `parsers`, `connections`, `format`, `output-disk`, `output-dir`),
naming the key: the 0.6 file cannot be kept. Publish the 1.0 file, then carry each value over.

| 0.6 key | 1.0 key | Conversion |
| --- | --- | --- |
| `output-disk` | `output.disk` | Same value |
| `output-dir` | `output.directory` | Same value. 0.6 defaulted to `public/openapi`, 1.0 to `openapi`: keep the 0.6 value if the file is served from there |
| `servers` | `servers` | `variable` → `variables` (0.6 never wrote them) |
| `servers.*.variable.<name>` | `servers.*.variables.<name>` | `enum`, `default`, `description` |
| `versions` | `documents` | One entry per document; the key stays the document name (`v1`) |
| `versions.<v>.servers` | `documents.<v>.servers` | Same shape as `servers` |
| `versions.<v>.output-file` | `documents.<v>.output.file` | Same value. With `languages`, 0.6 wrote `fr-openapi-v1.json`; 1.0 writes `openapi-v1.fr.json`, or `'{locale}-openapi-{document}.json'` keeps the 0.6 names |
| `versions.<v>.title` | `documents.<v>.info.title` | |
| `versions.<v>.description` | `documents.<v>.info.description` | |
| (the key `<v>`) | `documents.<v>.info.version` | 0.6 wrote the document key as `info.version`; set the real version (`'1.0.0'`) |
| `versions.<v>.routes` | `documents.<v>.routes.include` | Same list of URI masks (`'api/*'`) |
| `versions.<v>.ignore-verbs` | `documents.<v>.routes.ignored_methods` | Same list |
| `versions.<v>.name-by.by` | `documents.<v>.strategies.summary.by` | `description` → `doc`; `name`, `uri`, `controller` unchanged. See [Summaries and descriptions](#summaries-and-descriptions) |
| `versions.<v>.name-by.regex` | `documents.<v>.strategies.summary.pattern` | Same regex, its first group is kept |
| `versions.<v>.tag-by` | `documents.<v>.strategies.tags` | `by` unchanged, `regex` → `pattern` |
| `versions.<v>.group-by` | `documents.<v>.strategies.groups` | `by` unchanged, `regex` → `pattern`. Written as `Tag.parent` in 3.2, as `x-tagGroups` before only with `vendor_extensions: 'redoc'` |
| `versions.<v>.parameters.query.flat` | `documents.<v>.strategies.query.by` | `all`, `last`, `none` unchanged |
| `versions.<v>.use-ref` | `documents.<v>.use_references` | |
| `parsers.requests` | `extensions.request_extractors` | Delete the built-in entries (`DescribedRequestParser`, `RequestParser`); port the others ([Classes and contracts](#classes-and-contracts)) |
| `parsers.rules` | `extensions.rule_mappers` | Delete the JSON:API entries (`IncludesRuleParsers`, `FieldsRuleParsers`: built in); port the others |
| `parsers.responses` | `extensions.response_extractors` | Delete the built-in entries (every `Parsers\Responses\*`); port the others |
| `middlewares` | `middlewares` | Delete the built-in transformers (`ApplyBearerTokenSecurity`, `ApplyXsrfSecurity`, `ApplyCsrfSecurity`: `auth`, `auth:sanctum` and CSRF are mapped by default); port the others to `MiddlewareMapper` |
| `format.date` | `date_formats` | `Parameter::FORMAT_DATE` → `'date'`, `Parameter::FORMAT_DATETIME` → `'date-time'` |
| `connections.use-transaction` | `sampling.transactions` | |
| `connections.on-connections` | `sampling.connections` | Same list of drivers |
| `languages` | `languages` | Same list; see [Translations](#translations) |

New keys worth setting during the upgrade: `documents.<v>.openapi` (`'3.0.4'` keeps a 3.0 document for tools that
do not read 3.1), `documents.<v>.vendor_extensions` (`'redoc'` keeps the `x-tagGroups` of 0.6 before 3.2),
`documents.<v>.strategies.operation_id` (`method_name` keeps the `GET:users.index` of 0.6), `security_schemes`,
`exceptions.map`.

A converted file, 0.6:

```php skip
return [
    'output-disk' => 'public',
    'output-dir' => 'docs',
    'servers' => [['url' => env('APP_URL'), 'description' => 'API', 'variable' => []]],
    'versions' => [
        'v1' => [
            'output-file' => 'openapi-v1.json',
            'title' => 'Shop API',
            'description' => 'Public API of the shop',
            'routes' => ['api/v1/*'],
            'name-by' => ['by' => 'name', 'regex' => '/(.+)/'],
            'tag-by' => ['by' => 'controller', 'regex' => '/^App\\\\Http\\\\Controllers\\\\(\w+)Controller/'],
            'group-by' => ['by' => 'uri', 'regex' => '/^api\/v1\/(\w+)/'],
            'ignore-verbs' => ['HEAD', 'OPTIONS'],
            'parameters' => ['query' => ['flat' => 'last']],
            'use-ref' => true,
        ],
    ],
    'parsers' => [/* built-in entries only */],
    'middlewares' => [
        'auth:sanctum' => [ApplyBearerTokenSecurity::class, ApplyXsrfSecurity::class],
    ],
    'format' => ['date' => ['Y-m-d' => Parameter::FORMAT_DATE, 'Y-m-d H:i:s' => Parameter::FORMAT_DATETIME]],
    'connections' => ['use-transaction' => true, 'on-connections' => ['mysql', 'sqlite']],
    'languages' => ['en', 'fr'],
];
```

1.0 (only the changed keys of the published file are shown; keep the others as published):

```php skip
return [
    'documents' => [
        'v1' => [
            'openapi' => '3.1.2',
            'vendor_extensions' => 'redoc',
            'info' => [
                'title' => 'Shop API',
                'version' => '1.0.0',
                'description' => 'Public API of the shop',
            ],
            'routes' => [
                'include' => ['api/v1/*'],
                'ignored_methods' => ['HEAD', 'OPTIONS'],
            ],
            'strategies' => [
                'summary' => ['by' => 'doc'],
                'tags' => ['by' => 'controller', 'pattern' => '/^App\\\\Http\\\\Controllers\\\\(\w+)Controller/'],
                'groups' => ['by' => 'uri', 'pattern' => '/^api\/v1\/(\w+)/'],
                'operation_id' => ['by' => 'name'],
                'query' => ['by' => 'last'],
            ],
            'use_references' => true,
            'output' => ['file' => '{locale}-openapi-{document}.json'],
        ],
    ],
    'servers' => [['url' => env('APP_URL'), 'description' => 'API', 'variables' => []]],
    'output' => ['disk' => 'public', 'directory' => 'docs', 'file' => '{document}.json', 'format' => 'json'],
    'sampling' => ['transactions' => true, 'connections' => ['mysql', 'sqlite']],
    'date_formats' => ['Y-m-d' => 'date', 'Y-m-d H:i:s' => 'date-time'],
    'middlewares' => [],
    'languages' => ['en', 'fr'],
];
```

`summary` went from `name` to `doc` on purpose: see [Summaries and descriptions](#summaries-and-descriptions).

## Summaries and descriptions

| | 0.6 | 1.0 |
| --- | --- | --- |
| `summary` of an operation | the `name-by` strategy (the route name by default) | the PHPDoc summary of the action (strategy `doc`) |
| `description` | the PHPDoc **summary** (first line), or the `@oa-description` key | the PHPDoc **description** (the text after the first line) |

Keeping `strategies.summary.by: 'name'` keeps the 0.6 summaries, but the first line of the PHPDoc is then written
nowhere. Prefer `doc` and check that each action has a one-line summary; a PHPDoc with only a long first line moves
from the description to the summary. `#[Operation(summary: …, description: …)]` overrides both.

## PHPDoc tags

1.0 reads no `@oa-*` tag and reports none: a tag left in place is silently ignored. Replace every one.

| 0.6 tag | 1.0 | Notes |
| --- | --- | --- |
| `@oa-description <key>` | `#[Operation(description: 'openapi.requests.descriptions.<key>')]` | The text is translated when it is a translation key; or write the text itself |
| `@oa-response-status <code> [text]` | `#[Response(<code>, description: '<text>')]` | A `POST` returning a resource is already inferred as 201: drop the tag. For another status, add `resource:` or `type:`: a declared success replaces the inferred successes, and a status nothing inferred gets no body |
| `@oa-response-paginate` | `#[Response(resource: X::class, paginator: LengthAwarePaginator::class)]` | `X` is the resource of the collection |
| `@oa-response-paginate <Paginator class>` | `#[Response(resource: X::class, paginator: <Paginator class>::class)]` | `Paginator` (simple) or `CursorPaginator` |
| `@oa-response-header <Name> <value>` | `#[Response(<status>, headers: ['<Name>' => '<type>'])]` | The value becomes a PHPDoc type (`string`, `int`); several headers go in one map |

Kept as they were: the summary and description read from the PHPDoc, `@deprecated`, `@return` (now read in full:
generics, unions, array shapes), `@property` / `@mixin` / `@extends JsonResource<Model>` on resources. New: `@throws`
gives error responses.

## Attributes

All in `Ark4ne\OpenApi\Attributes`, as before.

| 0.6 | 1.0 | Notes |
| --- | --- | --- |
| `#[Response(statusCode: 201)]` | `#[Response(201)]` | `statusCode` → `status`. Repeatable: one attribute per status |
| `#[Response(statusText: 'Created')]` | `#[Response(201, description: 'Created')]` | 0.6 wrote a 200 in that case: always give the status |
| `#[Response(headers: ['X-Id' => '…'])]` | `#[Response(headers: ['X-Id' => 'int'])]` | Values are PHPDoc types; an array value of 0.6 (`['a', 'b']`) → `"'a'\|'b'"` |
| `#[Response(paginated: true)]` | `#[Response(resource: X::class, paginator: LengthAwarePaginator::class)]` | |
| `#[Response(paginated: CursorPaginator::class)]` | `#[Response(resource: X::class, paginator: CursorPaginator::class)]` | |
| `#[Response(description: '…')]` | `#[Response(description: '…')]` | Unchanged |
| `#[Id('user')]` | `#[SchemaName('User')]` | The component name; 1.0 names components after the class without `Resource` by default, so `#[Id]` can often be dropped |
| `#[ResourceFactory(factory, parameters, method)]` | `#[ResourceFactory(factory: …, parameters: […], method: …)]` | Same arguments; `factory` may now be any invokable class, not only a model factory |
| `#[Request(headers: [...])]` (never read by 0.6) | `#[Header('<name>', type: '…')]` per header | Or delete it |

## Classes and contracts

Delete every `use Ark4ne\OpenApi\…` of a 0.6 class: none exists in 1.0 under the same name.

| 0.6 | 1.0 |
| --- | --- |
| `Contracts\Documentation\DescribableRequest` (`describer()`, `describe(Describer): void`) | `Extraction\Requests\DescribesRequest`: `describe(RequestDescriber $request): RequestDescriber`; completes `rules()` instead of replacing it |
| `Descriptors\Requests\Describer` | `Extraction\Requests\RequestDescriber` (immutable: `return $request->…`) |
| `Describer::header($name)->required()->…`, `headers([...])` | `header($name, $rules, $description, required: true)` |
| `Describer::queries($rules)` | `query($rules)` |
| `Describer::body($rules)`, `json()`, `form()` | `body($rules, $mediaType)`, `json()`, `form()`, `multipart()`; drop the fields already in `rules()` |
| `Describer::xml($rules)` | `body($rules, 'text/xml')` |
| `Describer::accept($types)`, `encoding($encodings)` | the media type of `body()`; `header('Content-Encoding', 'in:gzip', required: true)` |
| `Describer::xsrf()` | nothing: the `auth:sanctum` mapper writes the XSRF scheme |
| `Describer::security()`, `token()`, `apiKey()`, `basic()`, `oauth2()`, `openId()` | the `auth` middleware of the route, or `#[Security('<scheme>')]` with the scheme in `security_schemes` |
| `Describer::rule($rules)->description('…')` | the rules alone; the description with `#[QueryParameter('<name>', description: '…')]` or a `DescribesRule` |
| `Contracts\CustomRuleParserContract`, `Contracts\CustomRuleParser` (deprecated) | `Extraction\Rules\RuleMapper` (`handles()`, `map(RuleInvocation, FieldDescription, GenerationContext): FieldDescription`) in `extensions.rule_mappers`, or `Extraction\Rules\DescribesRule` on the rule |
| `Documentation\Request\Parameter` (mutated by rule parsers) | `Description\FieldDescription` (immutable) holding a writer `Schema`: `$field->schema($field->schema->pattern(…))` |
| `Contracts\RequestParserContract` (`parse(Type, Entry): RequestEntry`) | `Extraction\Requests\RequestExtractor` (`priority()`, `supports(OperationContext)`, `extract(OperationContext, RequestDescription, GenerationContext): RequestDescription`) |
| `Contracts\ResponseParserContract` (`parse(Type, Entry): ResponseEntry`) | `Extraction\Responses\ResponseExtractor` (`priority()`, `supports(Type, OperationContext)`, `extract(Type, OperationContext, GenerationContext): list<ResponseDescription>`) |
| `Contracts\Transformer` (`transform(Entry, RequestEntry, array)`) | `Generation\MiddlewareMapper` (`handles()`, `map(Middleware, OperationDescription, DocumentDescription, GenerationContext): array{OperationDescription, DocumentDescription}`) |
| `Transformers\Middlewares\ApplyBearerTokenSecurity`, `ApplyXsrfSecurity`, `ApplyCsrfSecurity` | built in (`auth`, `auth:sanctum`, CSRF): delete them |
| `Contracts\Entry`, `Documentation\*Entry`, `Support\*` | no equivalent: extensions receive an `OperationContext` (route, method, path, action, PHPDoc, request and return types, `@throws`) and a `GenerationContext` (configuration, diagnostics, components, locale) |
| `Documentation\Request\Security`, `SecurityRequirement` | `Description\SecurityDescription` + writer `SecurityScheme`, or `security_schemes` in the configuration |
| `Support\Facades\Logger`, a thrown exception | `$context->diagnostics->warning(Stage::…, '…')`: an extension never throws for code it does not understand |
| `Providers\LaravelOpenApiProvider` | `Laravel\OpenApiServiceProvider`, auto-discovered: remove a manual registration from `bootstrap/providers.php` |

Every 1.0 extension is built through the container (constructor injection works) and is immutable: return the new
description, never mutate the one received. Interfaces with a priority (`RequestExtractor`, `ResponseExtractor`,
`OperationExtractor`, `Sampler`) declare it with `priority()` returning a public `PRIORITY` constant; the order of a
configuration array no longer matters.

## Translations

| 0.6 key | 1.0 |
| --- | --- |
| `openapi.requests.<route name>.name` | the key as the summary: `#[Operation(summary: 'openapi.requests.<route name>.name')]` |
| `openapi.requests.<route name>.description` | `#[Operation(description: 'openapi.requests.<route name>.description')]` |
| `openapi.requests.descriptions.<key>` (`@oa-description <key>`) | `#[Operation(description: 'openapi.requests.descriptions.<key>')]` |
| `openapi.requests.parameters.custom.<route name>.<field>`, `openapi.requests.parameters.<field>` | not read; [two extractors](examples.md#translation-keys-of-06) keep them for query, path and header parameters |

1.0 translates any summary, description or tag (and `#[Tag]` texts) that is a translation key, for each language of
`languages`; the lang files themselves do not change.

## What changes in the generated document

Expected differences when comparing the 0.6 document with the 1.0 one. They are not regressions.

- `openapi` is `3.1.2` (unless `documents.<v>.openapi` says otherwise): `nullable: true` becomes `type: [x, 'null']`,
  `exclusiveMinimum` a number.
- `info.version` is the configured version, not the document key.
- `operationId` is the route name (`users.index`), deduplicated (`users.index_2`… for a route with several methods),
  instead of `GET:users.index`. `strategies.operation_id.by: 'method_name'` writes the 0.6 form back.
- Summaries and descriptions moved: see [Summaries and descriptions](#summaries-and-descriptions).
- Component names: `User` instead of `resource-<hash>-user-resource`.
- Error responses are added: 401 (`auth`), 403 (`can`, `authorize()`, abilities), 404 (bound models), 419 (CSRF),
  422 with its schema (form requests), 429 (`throttle`), one per `@throws`; shared in `components.responses`.
- Optional path parameters (`{id?}`) give two paths.
- A `DELETE` returning a `Response` is a 204; a `POST` returning a resource is a 201.
- `Date` and `Cache-Control` headers of sampled responses are no longer written.
- JSON:API: `type` is a `const`, `id` is typed, `included` lists every reachable resource with a discriminator; the
  `x-ref`, `x-type`, `x-name` extensions are gone.
- `x-tagGroups` is written only with `vendor_extensions: 'redoc'` before 3.2 (`Tag.parent` in 3.2).
- Routes 0.6 documented by mistake may disappear (`routes.internal`: `up`, `sanctum/csrf-cookie`…, redirect and view
  routes).
