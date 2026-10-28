# Reference

Every attribute, every configuration key, every extension point and every command. The source is the final
authority: `vendor/ark4ne/laravel-openapi-generator/src/` (`Attributes/`, `Configuration/`, the interfaces of
`Extraction/`, `Sampling/` and `Generation/`). Examples: [recipes.md](recipes.md).

## What the generator reads

| Source | Gives |
| --- | --- |
| The route (URI, methods, name, domain, middlewares, `where` constraints) | the path, its parameters (`{id?}` gives a path with and without; `whereNumber` → `integer`, `whereUuid` → `uuid`, `whereIn` → `enum`), the operation id, the servers of a domain, the security and the 401/403/419/429 of the middlewares |
| The PHPDoc of the action | the summary (first line), the description (the rest), `@deprecated`, `@throws` (error responses), `@return` (the responses) |
| The parameters of the action | a form request (its `rules()`, `DescribesRequest`), the native types of the path parameters (`int`, a bound model and its 404, a backed enum) |
| The return type | the responses: `JsonResource`, `ResourceCollection`, `AnonymousResourceCollection<UserResource>`, paginators, `JsonResponse`, models, array shapes, scalars, files, streams, redirects, views, `Responsable`, unions (`CatResource\|DogResource` gives `oneOf` with a discriminator) |
| The resources | their schema, from `toArray()` run on sampled models (factories in a rolled back transaction, else fake values typed by the casts, `@property` and the database columns) |
| The attributes | everything below |

## Attributes

All are in `Ark4ne\OpenApi\Attributes`. Those that accept the controller class apply to each of its actions; the
action overrides them, and the attributes of a parent class or of a parent method are merged under those of the
child. Types are PHPDoc types (`int`, `positive-int`, `list<string>`, `'a'|'b'`, `array{id: int}`, a class).

### `#[Operation]`

On the controller or the action.

| Parameter | Effect |
| --- | --- |
| `summary` | replaces the summary of the PHPDoc or of the strategy |
| `description` | replaces the description (CommonMark) |
| `operationId` | replaces the operation id of the strategy |
| `tags` | replaces the tags of the strategy |
| `deprecated` | `true` / `false`, over `@deprecated` and `#[\Deprecated]` |
| `externalDocs` | URL of the external documentation |
| `externalDocsDescription` | its description |

### `#[Tag]`

Repeatable, on the controller or the action: adds a tag to the operation and describes it in the document.

| Parameter | Effect |
| --- | --- |
| `name` | name of the tag |
| `description` | description of the tag |
| `parent` | parent tag (`Tag.parent` in 3.2, `x-tagGroups` before with `redoc`) |
| `summary` | short summary (3.2) |
| `kind` | `nav`, `badge`… (3.2) |
| `externalDocs` | URL of the external documentation |
| `externalDocsDescription` | its description |

### `#[Response]`

Repeatable, on the controller, the action or an exception class. Replaces the response inferred for its status; on
an exception class, it is the response of the actions that declare `@throws` with it.

| Parameter | Effect |
| --- | --- |
| `status` | `int` or `'default'` (200 by default) |
| `description` | the HTTP phrase of the status by default |
| `resource` | a `JsonResource` or a `ResourceCollection` class giving the body |
| `type` | a PHPDoc type giving the body (`array{id: int}`, `list<string>`) |
| `mediaType` | `application/json` by default; `text/event-stream`: `type` is one event (`itemSchema` in 3.2) |
| `headers` | header name → PHPDoc type of its value |
| `paginator` | `LengthAwarePaginator`, `Paginator` or `CursorPaginator`: `resource` paginated (`data`, `links`, `meta`) |
| `summary` | short summary (3.2) |
| `example` | example of the body |

### `#[RequestBody]`

On the action: replaces or completes the body given by the rules.

| Parameter | Effect |
| --- | --- |
| `type` | a PHPDoc type giving the body |
| `resource` | a resource whose schema is the body (`{data}` for a JSON:API resource) |
| `mediaType` | media type of the body (`application/json` by default) |
| `description` | description of the body |
| `required` | `null`: required when the attribute gives the body, unchanged otherwise |

### `#[QueryParameter]`, `#[Header]`, `#[Cookie]`, `#[PathParameter]`

Repeatable. `#[PathParameter]` on the action only, the others on the controller or the action. A parameter of the
same name given by the rules is completed.

| Parameter | Effect |
| --- | --- |
| `name` | name of the parameter |
| `type` | PHPDoc type (`string` by default) |
| `description` | description |
| `required` | required (not for `#[PathParameter]`, always required) |
| `deprecated` | deprecated (`#[QueryParameter]` only) |
| `example` | example value |

### `#[Security]`

Repeatable, on the controller or the action: each attribute is an alternative requirement; on the action, it replaces
those of the class and of the middlewares.

| Parameter | Effect |
| --- | --- |
| `scheme` | name of a security scheme (`sanctum`, `passport`, one of `security_schemes`) |
| `scopes` | its scopes |

### `#[Exclude]`

On a controller, an action or a closure route: left out of every document.

### `#[Webhook]`

The route is documented under `webhooks` (3.1+; `x-webhooks` before with `redoc`), not under `paths`.

| Parameter | Effect |
| --- | --- |
| `name` | on an action: the key of the webhook (the route name by default); on a controller: the prefix of the keys of its actions (`order` + `paid` gives `order.paid`) |

### `#[Callback]`

Repeatable, on the action: a request sent back to a URL the client gave.

| Parameter | Effect |
| --- | --- |
| `name` | key of the callback in the operation |
| `url` | runtime expression of the URL (`{$request.body#/callback_url}`) |
| `method` | HTTP method (`POST` by default) |
| `webhook` | a webhook whose request and responses the callback reuses |
| `resource` | a resource giving the payload |
| `type` | a PHPDoc type giving the payload |
| `mediaType` | media type of the payload |
| `description` | description of the request |
| `status` | response expected from the receiver (200 by default) |

### `#[ResourceFactory]`

On a resource whose `toArray()` does not receive a model (a DTO, an array): builds the samples it receives.

| Parameter | Effect |
| --- | --- |
| `factory` | a model factory class (its `make()`), or any class, called through `method` or invoked |
| `parameters` | the attributes given to `make()`, or the arguments given by name to `method` / `__invoke` |
| `method` | the method of `factory` that builds the sample, `__invoke` by default |

### `#[SchemaName]`

On a resource or an enum: name of its component (the class name without `Resource` by default).

| Parameter | Effect |
| --- | --- |
| `name` | name of the component |

## Configuration

`config/openapi.php`, published with `php artisan vendor:publish --tag=openapi-config`. Read when a command runs: an
unknown top-level key or an invalid value fails the command with the key, the expected value and the received one.

| Key | Default | Meaning |
| --- | --- | --- |
| `documents` | `v1` | One entry per document; the key is its name (`--document`, `{document}`) |
| `documents.<name>.openapi` | `3.1.2` | Version written: `3.0.0` → `3.0.4`, `3.1.0` → `3.1.2`, `3.2.0` |
| `documents.<name>.strict` | `true` | Rejects what the version cannot write; `false` drops it with a warning |
| `documents.<name>.vendor_extensions` | `none` | `redoc`: `x-webhooks` before 3.1, `x-tagGroups` before 3.2 |
| `documents.<name>.info` | | `title`, `version`, `summary`, `description`, `terms_of_service`, `contact`, `license` |
| `documents.<name>.self` | `null` | Public URI of the document (`$self`, 3.2) |
| `documents.<name>.external_docs` | `null` | `url`, `description` |
| `documents.<name>.servers` | `[]` | Servers of the document; the global `servers` when empty |
| `documents.<name>.routes` | | `include` (`['api/*']`; `null` or `[]`: every route), `exclude`, `ignored_methods` (`HEAD`), `internal`, `order` (`registration`, `path`); masks on the URI, or a map of masks by `uri`, `name`, `domain`, `middleware` |
| `documents.<name>.strategies` | | `summary` (`doc`, `name`, `uri`, `controller`), `tags` (`controller`, `uri`, `name`, with `pattern`), `groups` (`null` or like `tags`), `operation_id` (`name`, `action`, `method_path`), `query` (`all`, `last`, `none`, `querystring`); `by` may be a class implementing the interface, built through the container |
| `documents.<name>.security_schemes` | `[]` | Security Scheme Objects (OpenAPI field names, camelCase) completing or replacing those of the middlewares |
| `documents.<name>.security` | `[]` | Schemes required by default on every operation |
| `documents.<name>.use_references` | `true` | Resources and enums as `components.schemas` referenced by `$ref` |
| `documents.<name>.output` | | Overrides of the global `output` |
| `documents.<name>.extensions` | `[]` | `x-*` extensions of the document |
| `servers` | `APP_URL` | `url`, `description`, `name` (3.2), `variables` (`default`, `enum`, `description`) |
| `output` | | `disk` (`null`: the default disk), `directory` (`openapi`), `file` (`{document}.json`, with `{document}` and `{locale}`), `format` (`json`, `yaml`) |
| `sampling` | | `transactions` (`true`), `connections` (drivers wrapped in a rolled back transaction), `collection_size` (`2`), `seed` (`1`), `examples` (`true`) |
| `date_formats` | | PHP date format → OpenAPI format (`date`, `date-time`), for `date_format:` and the sampled values |
| `middlewares` | `[]` | Middleware name or class → classes implementing `MiddlewareMapper`, replacing the built-in ones of that middleware |
| `extensions` | `[]` | `operation_extractors`, `request_extractors`, `rule_mappers`, `response_extractors`, `samplers`, `passes`: classes or bound interfaces, built through the container |
| `integrations` | | `json_api.errors` (`false`): error responses as JSON:API error documents |
| `exceptions` | | `read_throws` (`true`): read the `@throws` of the actions; `map`: exception class → status, or `['status' => …, 'description' => …]` |
| `languages` | `[]` | One document per locale; summaries, descriptions and tags may be translation keys |

## Extension points

Each is an interface. Register an implementation in `extensions` (application) or tag it in the container (package):
`openapi.operation-extractors`, `openapi.request-extractors`, `openapi.rule-mappers`, `openapi.response-extractors`,
`openapi.samplers`, `openapi.passes`. They are built through the container; an extractor or a sampler declares its
priority with `priority()`, returning a public `PRIORITY` constant.

| Interface | Namespace | Contract |
| --- | --- | --- |
| `OperationExtractor` | `Extraction\Operations` | `supports(OperationContext)`, `extract(OperationContext, OperationDescription, GenerationContext): OperationDescription`; every supporting extractor runs, by increasing priority |
| `RequestExtractor` | `Extraction\Requests` | `supports(OperationContext)`, `extract(OperationContext, RequestDescription, GenerationContext): RequestDescription`; every supporting extractor runs, by increasing priority |
| `RuleMapper` | `Extraction\Rules` | `handles(): list<string>` (rule names or classes), `map(RuleInvocation, FieldDescription, GenerationContext): FieldDescription`; replaces the built-in mapper of its rules |
| `ResponseExtractor` | `Extraction\Responses` | `supports(Type, OperationContext)`, `extract(Type, OperationContext, GenerationContext): list<ResponseDescription>`; the first supporting extractor, by decreasing priority |
| `Sampler` | `Sampling` | `supports(SampleRequest, GenerationContext)`, `sample(SampleRequest, GenerationContext): list<mixed>`; the first supporting sampler, by decreasing priority |
| `Pass` | `Generation` | `process(DocumentDescription, GenerationContext): DocumentDescription`; after the built-in passes, before the cleanup |
| `MiddlewareMapper` | `Generation` | `handles(): list<string>`, `map(Middleware, OperationDescription, DocumentDescription, GenerationContext): array{OperationDescription, DocumentDescription}`; listed in `middlewares` |
| `DescribesRequest` | `Extraction\Requests` | on a request class: `describe(RequestDescriber): RequestDescriber` (`header()`, `cookie()`, `query()`, `json()`, `form()`, `multipart()`, `body()`, `bodySchema()`, `bodyDescription()`, `parameter()`) |
| `DescribesRule` | `Extraction\Rules` | on a validation rule: `describe(FieldDescription): FieldDescription` |
| `SummaryStrategy`, `TagStrategy`, `GroupStrategy`, `OperationIdStrategy`, `QueryStrategy` | `Extraction\Strategies` | a class in `strategies.<name>.by` |

The description objects (`OperationDescription`, `RequestDescription`, `FieldDescription`…) are immutable: every
method returns a copy, so always use the return value. Their schemas are `Ark4ne\OpenApiWriter\Objects\Schema`
objects (`Schema::string()->pattern(…)`), written for the version of the document. An extension never throws for
application code it does not understand: it adds a diagnostic (`$context->diagnostics->warning(Stage::Request, …)`).

## Commands

| Command | Effect |
| --- | --- |
| `openapi:generate` | Every document, in each language. `--document=*`, `--strict` (fail on a warning), `--dry-run`, `--format=json\|yaml`, `--stdout` (one document; diagnostics on the error output). Exit 0 written, 1 a document failed, 2 invalid configuration or options |
| `openapi:inspect {route}` | What the generator understood of a route (its name, its URI, or its method and URI): the operation after the passes and all its diagnostics. `--document=` |
| `about` | Version, documents, JSON:API integration |

Diagnostics are grouped by route: warnings by default, infos with `-v`, only errors with `-q`.
