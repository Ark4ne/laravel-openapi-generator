# Upgrading from 0.6 to 1.0

1.0 is a rewrite. The configuration, the PHPDoc tags, the parser classes and the middleware transformers of 0.6 are
replaced; the documented behaviour is kept and extended. Plan to regenerate your documents and review the diff.

Coding agents: the package ships a migration skill, [`skills/laravel-openapi-generator-upgrade/`](skills/laravel-openapi-generator-upgrade/SKILL.md),
with a step-by-step procedure, the complete mapping and tested before/after examples.

## Requirements

| | 0.6 | 1.0 |
| --- | --- | --- |
| PHP | 8.1+ | 8.3+ |
| Laravel | 10, 11, 12, 13 | 13 |
| OpenAPI written | 3.0.2 | 3.0.0 → 3.0.4, 3.1.0 → 3.1.2, 3.2.0 (3.1.2 by default) |
| OpenAPI objects | `goldspecdigital/oooas` | `ark4ne/openapi-writer` |
| PHPDoc | `phpdocumentor/reflection-docblock` | `phpstan/phpdoc-parser` (generics, array shapes, conditional types) |

## Steps

1. `composer require ark4ne/laravel-openapi-generator:^1.0`
2. Move your `config/openapi.php` aside, publish the new one (`php artisan vendor:publish --tag=openapi-config`) and
   carry your values over with the table below. The command rejects unknown keys (`versions` among them) with the
   key, the expected value and the received one.
3. Replace the `@oa-*` tags and the `#[Response]` arguments (see [PHPDoc tags and attributes](#phpdoc-tags-and-attributes)).
4. Port your parsers and transformers to extractors, rule mappers and middleware mappers (see
   [Extensions](#extensions)).
5. `php artisan openapi:generate -v`, read the diagnostics, then `php artisan openapi:inspect <route>` for a route
   whose documentation surprises you.

## Configuration

Keys are now in `snake_case` (they were in `kebab-case`). `versions` becomes `documents`; the OpenAPI version is
chosen per document.

| 0.6 | 1.0 | Note |
| --- | --- | --- |
| `output-disk` | `output.disk` | |
| `output-dir` | `output.directory` | |
| `versions.<v>.output-file` | `documents.<v>.output.file` | Accepts `{document}` and `{locale}`; 0.6 prefixed the locale (`fr-openapi.json`), 1.0 inserts it before the extension |
| | `output.format`, `documents.<v>.output.format` | `json` or `yaml` (needs `symfony/yaml`) |
| `servers` | `servers` | `variable` becomes `variables` (0.6 never wrote the variables) |
| `versions` | `documents` | |
| | `documents.<v>.openapi`, `documents.<v>.strict` | 3.1.2 and strict by default; 0.6 always wrote 3.0.2 |
| | `documents.<v>.vendor_extensions` | `redoc`: `x-webhooks` before 3.1, `x-tagGroups` before 3.2; 0.6 always wrote `x-tagGroups` |
| `versions.<v>.servers` | `documents.<v>.servers` | |
| `versions.<v>.title` | `documents.<v>.info.title` | |
| `versions.<v>.description` | `documents.<v>.info.description` | |
| | `documents.<v>.info.version`, `summary`, `terms_of_service`, `contact`, `license` | 0.6 wrote the document key as `info.version` |
| `versions.<v>.routes` | `documents.<v>.routes.include` | The same URI masks, or a map of masks by `uri`, `name`, `domain`, `middleware` |
| | `documents.<v>.routes.exclude` | Same shape as `include` |
| `versions.<v>.ignore-verbs` | `documents.<v>.routes.ignored_methods` | |
| | `documents.<v>.routes.internal` | Routes of Laravel and of common packages, never documented (`up`, `sanctum/csrf-cookie`…) |
| | `documents.<v>.routes.order` | `registration` (default) or `path` |
| `versions.<v>.name-by` (`by`, `regex`) | `documents.<v>.strategies.summary` (`by`, `pattern`) | `description` becomes `doc` |
| `versions.<v>.tag-by` | `documents.<v>.strategies.tags` | `regex` becomes `pattern` |
| `versions.<v>.group-by` | `documents.<v>.strategies.groups` | `Tag.parent` in 3.2, `x-tagGroups` before (with `redoc`) |
| | `documents.<v>.strategies.operation_id` | `name`, `action`, `method_path` or `method_name`; 0.6 wrote `"<method>:<route name>"`, which is `method_name` |
| `versions.<v>.parameters.query.flat` | `documents.<v>.strategies.query` | Same values (`all`, `last`, `none`), plus `querystring` (3.2) |
| `versions.<v>.use-ref` | `documents.<v>.use_references` | |
| | `documents.<v>.security_schemes`, `documents.<v>.security` | Schemes completing those of the middlewares, schemes required by default |
| | `documents.<v>.self`, `external_docs`, `extensions` | `$self` (3.2), external docs and `x-*` of the document |
| `parsers.requests` | `extensions.request_extractors` | Classes with a priority, no longer an ordered map |
| `parsers.rules` | `extensions.rule_mappers` | Or the `DescribesRule` interface on the rule |
| `parsers.responses` | `extensions.response_extractors` | |
| `middlewares` (transformers) | `middlewares` (`MiddlewareMapper`) | Built-in mappers cover `auth` and its guards (Sanctum, Passport…), `auth.basic`, `signed`, Sanctum abilities, Passport scopes and CSRF; `throttle` and `can` add their 429 and 403 |
| `format.date` | `date_formats` | Values are OpenAPI formats (`date`, `date-time`), not `Parameter` constants |
| `connections.use-transaction` | `sampling.transactions` | |
| `connections.on-connections` | `sampling.connections` | Drivers whose connections are wrapped in a rolled back transaction |
| | `sampling.collection_size`, `sampling.seed`, `sampling.examples` | |
| `languages` | `languages` | |
| | `extensions.operation_extractors`, `samplers`, `passes` | New extension points |
| | `exceptions.read_throws`, `exceptions.map` | `@throws` of the actions become error responses |
| | `integrations.json_api.errors` | JSON:API error documents (with `ark4ne/laravel-json-api`) |

## PHPDoc tags and attributes

The PHPDoc of the action still gives the texts, but not the same ones: 0.6 took the summary from the `name-by`
strategy (the route name by default) and the description from the first line of the PHPDoc; 1.0 takes the summary
from the first line (strategy `doc`) and the description from the rest. `@deprecated` deprecates the operation.
Every other piece of metadata is a PHP attribute of `Ark4ne\OpenApi\Attributes`; 1.0 ignores the `@oa-*` tags
without a warning, so remove each one.

| 0.6 | 1.0 |
| --- | --- |
| `@oa-description <key>` | `#[Operation(description: 'openapi.requests.descriptions.<key>')]` (a translation key is translated) |
| `@oa-response-status 204` | `#[Response(204)]` (a `POST` returning a resource is already a 201) |
| `@oa-response-paginate [Paginator class]` | `#[Response(resource: UserResource::class, paginator: LengthAwarePaginator::class)]` |
| `@oa-response-header X-Id 1` | `#[Response(headers: ['X-Id' => 'int'])]` |
| `#[Response(statusCode: 201)]` | `#[Response(201)]` (the argument is `status`; it is repeatable, one per status) |
| `#[Response(statusText: 'Created')]` | `#[Response(201, description: 'Created')]` (0.6 wrote a 200 there) |
| `#[Response(headers: ['X-Id' => '…'])]` | `#[Response(headers: ['X-Id' => 'int'])]` (values are PHPDoc types) |
| `#[Response(paginated: true)]` | `#[Response(resource: UserResource::class, paginator: LengthAwarePaginator::class)]` |
| `#[Id('user')]` on a resource | `#[SchemaName('User')]` |
| `#[ResourceFactory(...)]` | `#[ResourceFactory(factory: …, parameters: […], method: …)]` |
| | `#[Operation]`, `#[Tag]`, `#[Security]`, `#[Exclude]`, `#[QueryParameter]`, `#[PathParameter]`, `#[Header]`, `#[Cookie]`, `#[RequestBody]`, `#[Webhook]`, `#[Callback]` |

Attributes may be put on the controller class (they apply to every action) and on the action (it overrides them).
The `@return` type of the action is read in full (`AnonymousResourceCollection<UserResource>`,
`CatResource|DogResource`, array shapes) and drives the responses.

## Translations

0.6 looked up fixed keys: `openapi.requests.<route name>.name` and `.description`, `openapi.requests.descriptions.<key>`
and `openapi.requests.parameters.<field>`. 1.0 translates the texts themselves: a summary, a description or a tag
that is a translation key is translated in each language of `languages`. Write the key where the text goes
(`#[Operation(summary: 'openapi.requests.users.index.name')]`), or keep the 0.6 keys with two small extractors (see
the upgrade skill below).

## Requests

| 0.6 | 1.0 |
| --- | --- |
| `Contracts\Documentation\DescribableRequest` (`describer()`, `describe(Describer)`) | `Extraction\Requests\DescribesRequest`: `describe(RequestDescriber $request): RequestDescriber`, the describer being immutable |
| `Contracts\CustomRuleParser` on a rule | `Extraction\Rules\DescribesRule`: `describe(FieldDescription $field): FieldDescription` |
| `Parsers\Rules\*` registered in `parsers.rules` | One `Extraction\Rules\RuleMapper` per rule (`handles()`, `map()`), in `extensions.rule_mappers` |

```php
use Ark4ne\OpenApi\Extraction\Requests\DescribesRequest;
use Ark4ne\OpenApi\Extraction\Requests\RequestDescriber;

final class SearchRequest extends FormRequest implements DescribesRequest
{
    public function describe(RequestDescriber $request): RequestDescriber
    {
        return $request->header('X-Tenant', 'required|uuid')->query(['include' => 'string']);
    }
}
```

## Extensions

Every extension point is an interface; implementations declare their priority (`priority()` returning a public
`PRIORITY` constant) instead of depending on the order of a configuration array. They are listed in `extensions`
or tagged in the container by packages, and built through the container.

| 0.6 | 1.0 | Registry |
| --- | --- | --- |
| `parsers.requests` | `Extraction\Requests\RequestExtractor` | every supporting extractor runs, by increasing priority |
| `parsers.rules` | `Extraction\Rules\RuleMapper` | indexed by rule name or class; a later mapper replaces an earlier one |
| `parsers.responses` | `Extraction\Responses\ResponseExtractor` | the first supporting extractor, by decreasing priority |
| `middlewares` transformers | `Generation\MiddlewareMapper` | by middleware name or class |
| | `Extraction\Operations\OperationExtractor` | every supporting extractor runs, by increasing priority |
| | `Sampling\Sampler` | the first supporting sampler, by decreasing priority |
| | `Generation\Pass` | in order, after the built-in passes |

An extractor never throws for application code it does not understand: it adds a diagnostic
(`$context->diagnostics->warning(...)`), shown by `openapi:generate`.

## What changes in the documents

- The version is 3.1.2 unless you choose another one; `nullable`, `exclusiveMinimum`, several types and `const` are
  written for the version.
- `operationId` is unique (`users.index`, deduplicated: a route with several methods, like `Route::any()`, gives
  `users.index`, `users.index_2`…) instead of `"GET:users.index"`. Set `strategies.operation_id` to `method_name` to
  keep the 0.6 identifiers.
- 401, 403, 404, 419, 422 and 429 responses are added from the middlewares, the bound models and the validation, as
  shared `components.responses`; the 422 has a schema.
- Optional path parameters (`{id?}`) give one path with and one without them.
- Resources are components named after the class without `Resource` (`User`), not `resource-<hash>-user-resource`.
- The `Date` and `Cache-Control` headers of the sampled responses are no longer written.
- JSON:API resources: `type` is a `const`, `id` is typed, `included` lists every reachable resource with a
  discriminator, and the `x-ref`, `x-type` and `x-name` extensions are gone.
