# Examples: v0.6 code and its 1.0 port

Each example shows the 0.6 code (`php skip`, it does not run in 1.0), then its 1.0 port. The package's test suite
runs every 1.0 block as `routes/api.php` of a Laravel application (its routes under the `api` prefix), generates its
document with the published configuration in 3.0, 3.1 and 3.2, and validates it against the official schemas. In an
application each class goes to its own file; the namespaces here only keep the examples apart. Mapping tables:
[mapping.md](mapping.md).

## PHPDoc tags and response attributes

The `@oa-*` tags and the arguments of the 0.6 `#[Response]` become arguments of the 1.0 `#[Response]`; `#[Id]`
becomes `#[SchemaName]`.

0.6:

```php skip
/** @mixin Order */
#[Id('order')]
class OrderResource extends JsonResource { /* … */ }

class OrderController
{
    /**
     * List the orders.
     *
     * @oa-response-paginate
     * @return AnonymousResourceCollection<OrderResource>
     */
    public function index(): AnonymousResourceCollection { /* … */ }

    /**
     * Create an order.
     *
     * @oa-response-status 201
     * @oa-response-header Location /api/orders/1
     */
    public function store(StoreOrderRequest $request): OrderResource { /* … */ }

    #[Response(statusCode: 202, statusText: 'Accepted', headers: ['Retry-After' => '120'], description: 'Export queued')]
    public function export(): JsonResponse { /* … */ }
}
```

1.0:

```php
namespace App\Sales;

use Ark4ne\OpenApi\Attributes\Response;
use Ark4ne\OpenApi\Attributes\SchemaName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;

/**
 * @property int $id
 * @property string $reference
 * @property float $total
 */
final class Order extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['total' => 'float'];
    }
}

/**
 * @mixin Order
 */
#[SchemaName('Order')]
final class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'reference' => $this->reference, 'total' => $this->total];
    }
}

final class StoreOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return ['reference' => 'required|string|max:32', 'total' => 'required|numeric|min:0'];
    }
}

final class OrderController
{
    /**
     * List the orders.
     *
     * @return AnonymousResourceCollection<OrderResource>
     */
    #[Response(resource: OrderResource::class, paginator: LengthAwarePaginator::class)]
    public function index(): AnonymousResourceCollection
    {
        return OrderResource::collection(Order::query()->paginate());
    }

    /**
     * Create an order.
     */
    #[Response(201, resource: OrderResource::class, headers: ['Location' => 'string'])]
    public function store(StoreOrderRequest $request): OrderResource
    {
        return new OrderResource(Order::query()->create($request->validated()));
    }

    /**
     * Export the orders.
     */
    #[Response(202, description: 'Export queued', headers: ['Retry-After' => 'int'])]
    public function export(): JsonResponse
    {
        return new JsonResponse(null, 202, ['Retry-After' => 120]);
    }
}

Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
Route::post('orders/export', [OrderController::class, 'export'])->name('orders.export');
```

What changed:

- `@oa-response-paginate` (or `paginated: true`) → `paginator:` with the class, and `resource:`: the return type does
  not tell which resource is paginated.
- `@oa-response-status 201` → `#[Response(201, resource: …)]`. A declared success replaces the successes inferred
  for other statuses: without `resource:` (or `type:`), the 201 has no body when the generator inferred a 200.
- Header values are PHPDoc types (`string`, `int`), no longer example values.
- `statusCode` → `status` (first positional argument), `statusText` → `description`.

## Described requests

0.6 `DescribableRequest` replaced `rules()` and mutated a `Describer`; 1.0 `DescribesRequest` **completes** `rules()`
and returns an immutable `RequestDescriber`.

0.6:

```php skip
class ImportRequest extends FormRequest implements DescribableRequest
{
    public function rules(): array
    {
        return ['file' => 'required|file|mimes:csv'];
    }

    public function describer(): Describer
    {
        return tap(new Describer(), $this->describe(...));
    }

    public function describe(Describer $descriptor): void
    {
        $descriptor
            ->headers(['X-Tenant' => fn(Parameter $header) => $header->required()->description('Tenant of the import')])
            ->encoding(['gzip'])
            ->queries(['dry_run' => $descriptor->rule('boolean')->description('Validate without importing')])
            ->body($this->rules())
            ->token();
    }
}
```

1.0:

```php
namespace App\Imports;

use Ark4ne\OpenApi\Attributes\QueryParameter;
use Ark4ne\OpenApi\Extraction\Requests\DescribesRequest;
use Ark4ne\OpenApi\Extraction\Requests\RequestDescriber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;

final class ImportRequest extends FormRequest implements DescribesRequest
{
    public function rules(): array
    {
        return ['file' => 'required|file|mimes:csv'];
    }

    public function describe(RequestDescriber $request): RequestDescriber
    {
        return $request
            ->header('X-Tenant', 'uuid', 'Tenant of the import', required: true)
            ->header('Content-Encoding', 'in:gzip', required: true)
            ->query(['dry_run' => 'boolean']);
    }
}

final class ImportController
{
    /**
     * Import orders from a CSV file.
     */
    #[QueryParameter('dry_run', type: 'bool', description: 'Validate without importing')]
    public function store(ImportRequest $request): void {}
}

Route::post('imports', [ImportController::class, 'store'])->middleware('auth:sanctum')->name('imports.store');
```

What changed:

- `describer()` is gone; `describe()` returns the describer it received, completed (`return $request->…`).
- `body($this->rules())` is gone: the rules are read anyway. Keep in `describe()` only the fields **not** in `rules()`.
- `headers([name => closure])` / `header($name)->required()` → `header($name, $rules, $description, required: true)`;
  `encoding()` and `accept()` → a header with an `in:` rule, or the media type of `body($rules, $mediaType)`.
- `queries()` → `query()`; `json()`, `form()` keep their name; `xml($rules)` → `body($rules, 'text/xml')`.
- `rule(…)->description(…)` has no equivalent in the describer: describe the parameter with `#[QueryParameter]`,
  which completes the parameter of the same name.
- `token()`, `apiKey()`, `basic()`, `oauth2()`, `openId()`, `security()` → the `auth` middleware of the route (here
  `auth:sanctum`), or `#[Security('scheme')]` with the scheme in `security_schemes`.

## Custom rule parsers

A 0.6 `CustomRuleParserContract` registered in `parsers.rules` becomes a `RuleMapper` (the rule is a class of a
package, or a parser is shared) or `DescribesRule` on the rule itself (the rule belongs to the application).

0.6:

```php skip
// config/openapi.php: 'parsers' => ['rules' => [App\Rules\Slug::class => App\Docs\SlugRuleParser::class]]
class SlugRuleParser implements CustomRuleParserContract
{
    public function parse(Parameter $parameter, ValidationRule|Rule $rule, array $parameters, array $rules): void
    {
        $parameter->string()->pattern('^[a-z0-9-]+$')->description('A slug');
    }
}
```

1.0:

```php
namespace App\Slugs;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;

final class Slug implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^[a-z0-9-]+$/', $value) !== 1) {
            $fail('The :attribute must be a slug.');
        }
    }
}

final class SlugRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return [Slug::class];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        return $field->schema($field->schema->type('string')->pattern('^[a-z0-9-]+$')->description('A slug'));
    }
}

final class StorePageRequest extends FormRequest
{
    public function rules(): array
    {
        return ['slug' => ['required', new Slug()]];
    }
}

final class PageController
{
    /**
     * Create a page.
     */
    public function store(StorePageRequest $request): void {}
}

// An application lists the mapper in `extensions.rule_mappers` of config/openapi.php; a package tags it.
app()->tag([SlugRuleMapper::class], 'openapi.rule-mappers');

Route::post('pages', [PageController::class, 'store'])->name('pages.store');
```

The same mapping on the rule itself, without registration:

```php skip
final class Slug implements ValidationRule, DescribesRule
{
    // validate() …

    public function describe(FieldDescription $field): FieldDescription
    {
        return $field->schema($field->schema->type('string')->pattern('^[a-z0-9-]+$'));
    }
}
```

`Parameter` calls of 0.6 → writer `Schema` methods: `string()`, `int()`, `bool()`, `array()`, `object()` →
`type('string')`, `type('integer')`…; `min()` / `max()` → `minimum()` / `maximum()` (numbers), `minLength()` /
`maxLength()` (strings), `minItems()` / `maxItems()` (arrays); `enum([…])` → `enum(...$values)`; `pattern()`,
`format()`, `description()`, `example()`, `default()` keep their name; `required()` → `$field->required()`.

## Middleware transformers

A 0.6 `Transformer` listed under `middlewares` becomes a `MiddlewareMapper` listed under the same key. The built-in
mappers already cover `auth` (and its guards), `auth.basic`, `signed`, CSRF, Sanctum abilities, Passport scopes,
`throttle` and `can`: delete the 0.6 entries `ApplyBearerTokenSecurity`, `ApplyXsrfSecurity`, `ApplyCsrfSecurity`.
Port only the transformers of the application.

0.6:

```php skip
// config/openapi.php: 'middlewares' => ['api.key' => [App\Docs\ApplyApiKeySecurity::class]]
class ApplyApiKeySecurity implements Transformer
{
    public function transform(Entry $entry, RequestEntry $request, array $responses): void
    {
        $request->addSecurity((new SecurityRequirement())->addSecurity(
            Security::component('ApiKey', fn(Security $security) => $security
                ->type(Security::TYPE_API_KEY)->name('X-Api-Key')->in(Security::IN_HEADER))
        ));
    }
}
```

1.0:

```php
namespace App\ApiKeys;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\SecurityDescription;
use Ark4ne\OpenApi\Generation\Middleware;
use Ark4ne\OpenApi\Generation\MiddlewareMapper;
use Ark4ne\OpenApiWriter\Objects\SecurityScheme;
use Illuminate\Support\Facades\Route;

final class ApiKeyMiddlewareMapper implements MiddlewareMapper
{
    public function handles(): array
    {
        return ['api.key'];
    }

    public function map(Middleware $middleware, OperationDescription $operation, DocumentDescription $document, GenerationContext $context): array
    {
        if (! isset($document->securitySchemes['apiKey'])) {
            $document = $document->securityScheme('apiKey', SecurityScheme::apiKey('apiKey')->name('X-Api-Key')->in('header'));
        }

        return [$operation->addSecurity(SecurityDescription::create(['apiKey' => []])), $document];
    }
}

final class StatusController
{
    /**
     * Status of the partner account.
     *
     * @return array{active: bool}
     */
    public function show(): array
    {
        return ['active' => true];
    }
}

Route::get('partner/status', [StatusController::class, 'show'])->middleware('api.key')->name('partner.status');
```

```php skip
// config/openapi.php
'middlewares' => [
    'api.key' => [App\ApiKeys\ApiKeyMiddlewareMapper::class],
],
```

Without code: declare the scheme in `documents.<name>.security_schemes`
(`'apiKey' => ['type' => 'apiKey', 'name' => 'X-Api-Key', 'in' => 'header']`) and put `#[Security('apiKey')]` on the
controllers.

## Translation keys of 0.6

0.6 looked up fixed keys: `openapi.requests.<route name>.name` (summary), `openapi.requests.<route name>.description`
(description), `openapi.requests.descriptions.<key>` (the key of `@oa-description`),
`openapi.requests.parameters.custom.<route name>.<field>` and `openapi.requests.parameters.<field>` (parameter
descriptions). 1.0 translates the texts themselves: a summary, a description or a tag that is a translation key is
translated in each language of `languages`.

The simple port: write the key where the text goes, `#[Operation(summary: 'openapi.requests.orders.index.name')]`,
`#[Operation(description: 'openapi.requests.descriptions.orders')]` for `@oa-description orders`, or the key as the
first line of the PHPDoc. When many routes rely on the 0.6 keys, keep them with two extractors:

```php
namespace App\Legacy;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Description\RequestDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Operations\OperationExtractor;
use Ark4ne\OpenApi\Extraction\Requests\RequestExtractor;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;

/**
 * The summary and the description of the 0.6 keys `openapi.requests.<route name>.name` and `.description`.
 */
final readonly class LegacyOperationTranslations implements OperationExtractor
{
    public const PRIORITY = 900;

    public function __construct(private Translator $translator) {}

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(OperationContext $operation): bool
    {
        return $operation->route->name !== null;
    }

    public function extract(OperationContext $operation, OperationDescription $description, GenerationContext $context): OperationDescription
    {
        $key = "openapi.requests.{$operation->route->name}";

        return $description
            ->summary($this->line("{$key}.name", $context) ?? $description->summary)
            ->description($this->line("{$key}.description", $context) ?? $description->description);
    }

    private function line(string $key, GenerationContext $context): ?string
    {
        $line = $this->translator->get($key, [], $context->locale);

        return is_string($line) && $line !== $key ? $line : null;
    }
}

/**
 * The parameter descriptions of the 0.6 keys `openapi.requests.parameters.custom.<route name>.<name>` and
 * `openapi.requests.parameters.<name>`.
 */
final readonly class LegacyParameterTranslations implements RequestExtractor
{
    public const PRIORITY = 900;

    public function __construct(private Translator $translator) {}

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(OperationContext $operation): bool
    {
        return true;
    }

    public function extract(OperationContext $operation, RequestDescription $request, GenerationContext $context): RequestDescription
    {
        return $request->parameters(...array_map(fn(ParameterDescription $parameter): ParameterDescription => $parameter->description(
            $this->line("openapi.requests.parameters.custom.{$operation->route->name}.{$parameter->name}", $context)
                ?? $this->line("openapi.requests.parameters.{$parameter->name}", $context)
                ?? $parameter->description,
        ), $request->parameters));
    }

    private function line(string $key, GenerationContext $context): ?string
    {
        $line = $this->translator->get($key, [], $context->locale);

        return is_string($line) && $line !== $key ? $line : null;
    }
}

final class ListInvoicesRequest extends FormRequest
{
    public function rules(): array
    {
        return ['status' => 'in:draft,paid'];
    }
}

final class InvoiceController
{
    /**
     * @return list<array{id: int}>
     */
    public function index(ListInvoicesRequest $request): array
    {
        return [];
    }
}

// An application lists them in `extensions.operation_extractors` and `extensions.request_extractors`.
app()->tag([LegacyOperationTranslations::class], 'openapi.operation-extractors');
app()->tag([LegacyParameterTranslations::class], 'openapi.request-extractors');

Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
```

They run after the built-in extractors (priority 900) and before the translation of the texts (1000). Body fields
are not covered: describe them with `DescribesRule`, a `RuleMapper` or `#[RequestBody]`.
