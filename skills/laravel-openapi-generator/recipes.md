# Recipes

Each recipe is one complete `php` block: the classes it needs, then its routes. The package's test suite runs every
block in a Laravel application, generates its document in 3.0, 3.1 and 3.2, and validates it against the official
schemas. In an application, each class goes to its own file (`app/Models/`, `app/Http/…`) and the routes to
`routes/api.php`; the namespaces here only keep the recipes apart.

Blocks marked `php skip` are fragments (configuration, service provider, tests), not run.

## CRUD resource

The return type of the action gives the response, the form request gives the body, the route parameters give the path
parameters (a bound model also gives a 404), the middlewares give the security and the 401, the validation the 422.

```php
namespace App\Shop;

use Ark4ne\OpenApi\Attributes\Response;
use Ark4ne\OpenApi\Attributes\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;

/**
 * @property int $id
 * @property string $title
 * @property float $price
 * @property \Illuminate\Support\Carbon|null $published_at
 */
final class Book extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['price' => 'float', 'published_at' => 'datetime'];
    }
}

/**
 * @mixin Book
 */
final class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'price' => $this->price,
            'published_at' => $this->published_at,
        ];
    }
}

final class StoreBookRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'tags' => 'array',
            'tags.*' => 'string|distinct',
        ];
    }
}

#[Tag('books', description: 'The books of the shop')]
final class BookController
{
    /**
     * List the books.
     *
     * @return AnonymousResourceCollection<BookResource>
     */
    #[Response(200, resource: BookResource::class, paginator: LengthAwarePaginator::class)]
    public function index(): AnonymousResourceCollection
    {
        return BookResource::collection(Book::query()->paginate());
    }

    /**
     * Show one book.
     */
    public function show(Book $book): BookResource
    {
        return new BookResource($book);
    }

    /**
     * Add a book.
     */
    public function store(StoreBookRequest $request): BookResource
    {
        return new BookResource(Book::query()->create($request->validated()));
    }

    /**
     * Remove a book.
     */
    #[Response(204)]
    public function destroy(Book $book): HttpResponse
    {
        $book->delete();

        return response()->noContent();
    }
}

Route::middleware('auth:sanctum')->group(static function (): void {
    Route::get('api/books', [BookController::class, 'index'])->name('books.index');
    Route::post('api/books', [BookController::class, 'store'])->name('books.store');
    Route::get('api/books/{book}', [BookController::class, 'show'])->name('books.show');
    Route::delete('api/books/{book}', [BookController::class, 'destroy'])->name('books.destroy');
});
```

## Declared responses and exceptions

`#[Response]` declares what the return type cannot say, one per status; it replaces the response inferred for its
status. Each `@throws` of the action becomes an error
response: the `#[Response]` of the exception class, the `exceptions.map` of the configuration, or what Laravel and
Symfony render (`ModelNotFoundException` → 404, `AuthorizationException` → 403, `HttpException` subclasses).

```php
namespace App\Booking;

use Ark4ne\OpenApi\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use RuntimeException;

#[Response(409, description: 'The seat is already taken')]
final class SeatTakenException extends RuntimeException {}

final class SeatController
{
    /**
     * Hold a seat for 15 minutes.
     *
     * @throws SeatTakenException
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    #[Response(200, description: 'Seat held', type: 'array{seat: string, expires_at: string}', headers: ['Retry-After' => 'int'])]
    public function hold(string $seat): JsonResponse
    {
        return new JsonResponse(['seat' => $seat, 'expires_at' => now()->addMinutes(15)->toAtomString()], headers: ['Retry-After' => 900]);
    }
}

Route::post('api/seats/{seat}/hold', [SeatController::class, 'hold'])->name('seats.hold');
```

## Requests described by hand

`DescribesRequest` completes the rules (headers, cookies, query, other media types); `#[QueryParameter]`, `#[Header]`
and `#[PathParameter]` do the same on the action; a rule of the application describes itself with `DescribesRule`.

```php
namespace App\Search;

use Ark4ne\OpenApi\Attributes\PathParameter;
use Ark4ne\OpenApi\Attributes\QueryParameter;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Requests\DescribesRequest;
use Ark4ne\OpenApi\Extraction\Requests\RequestDescriber;
use Ark4ne\OpenApi\Extraction\Rules\DescribesRule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;

final class Isbn implements DescribesRule, ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^[0-9-]{10,17}$/', $value) !== 1) {
            $fail('The :attribute must be an ISBN.');
        }
    }

    public function describe(FieldDescription $field): FieldDescription
    {
        return $field->schema($field->schema->type('string')->pattern('^[0-9-]{10,17}$'));
    }
}

final class SearchRequest extends FormRequest implements DescribesRequest
{
    public function rules(): array
    {
        return ['q' => 'required|string|min:2', 'isbn' => [new Isbn()], 'page' => 'integer|min:1'];
    }

    public function describe(RequestDescriber $request): RequestDescriber
    {
        return $request->header('X-Tenant', 'required|uuid', 'Tenant of the request');
    }
}

final class SearchController
{
    /**
     * Search the catalog.
     *
     * @return list<array{id: int, title: string}>
     */
    #[QueryParameter('sort', type: "'title'|'price'", description: 'Sort order')]
    #[PathParameter('catalog', type: 'non-empty-string', description: 'Catalog code', example: 'fr')]
    public function search(SearchRequest $request, string $catalog): array
    {
        return [];
    }
}

Route::get('api/catalogs/{catalog}/search', [SearchController::class, 'search'])->name('catalogs.search');
```

## Webhooks and callbacks

A webhook is a request the application sends. Describe it on a route whose action reads it as it would be received
(its form request is the payload) and mark it `#[Webhook]`; the route is documented under `webhooks` (3.1+,
`x-webhooks` with `redoc` before), never under `paths`. `#[Callback]` documents a request sent back to a URL the
client gave, reusing a webhook or describing its own payload.

The webhook routes must not answer real requests: register them only for the console (where the documents are
generated), or protect them with a middleware.

```php
namespace App\Orders;

use Ark4ne\OpenApi\Attributes\Callback;
use Ark4ne\OpenApi\Attributes\Header;
use Ark4ne\OpenApi\Attributes\Webhook;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

final class OrderPayload extends FormRequest
{
    public function rules(): array
    {
        return ['id' => 'required|integer', 'total' => 'required|numeric', 'status' => 'required|in:paid,refunded'];
    }
}

#[Webhook('order')]
final class OrderWebhooks
{
    /**
     * An order was paid.
     */
    #[Header('X-Signature', type: 'string', required: true, description: 'HMAC of the body')]
    public function paid(OrderPayload $payload): void {}
}

final class OrderController
{
    /**
     * Place an order; the shop calls `callback_url` once it is paid.
     */
    #[Callback('orderPaid', url: '{$request.body#/callback_url}', webhook: 'order.paid')]
    public function store(): JsonResponse
    {
        return new JsonResponse(['id' => 1], 201);
    }
}

if (app()->runningInConsole()) {
    Route::post('webhooks/orders/paid', [OrderWebhooks::class, 'paid']);
}

Route::post('api/orders', [OrderController::class, 'store'])->name('orders.store');
```

## Extending the generator

Every registry takes classes of the application: in `extensions` of `config/openapi.php`, or tagged in the container
by a package. They are built through the container (their dependencies injected) and declare their priority.

```php
namespace App\Docs;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Generation\Pass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;

/**
 * `phone`, a rule registered with Validator::extend(): an E.164 phone number.
 */
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

/**
 * Every operation gets the API version it belongs to.
 */
final class ApiVersionPass implements Pass
{
    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        return $document->operations(...array_map(
            static fn(OperationDescription $operation): OperationDescription => $operation->x('api-version', '2024-06'),
            $document->operations,
        ));
    }
}

final class ContactRequest extends FormRequest
{
    public function rules(): array
    {
        return ['phone' => 'required|phone'];
    }
}

final class ContactController
{
    /**
     * Ask to be called back.
     */
    public function store(ContactRequest $request): void {}
}

// In a service provider of a package; an application lists the classes in `extensions` instead.
app()->tag([PhoneRuleMapper::class], 'openapi.rule-mappers');
app()->tag([ApiVersionPass::class], 'openapi.passes');

Route::post('api/contact', [ContactController::class, 'store'])->name('contact.store');
```

The same extensions in the configuration of the application:

```php skip
// config/openapi.php
'extensions' => [
    'rule_mappers' => [App\Docs\PhoneRuleMapper::class],
    'passes' => [App\Docs\ApiVersionPass::class],
],
```

## JSON:API resources

With `ark4ne/laravel-json-api` installed, its resources are written in `application/vnd.api+json` as resource
objects (`id`, `type`, `attributes`, `relationships`), the descriptors (`$this->string()`, `$this->date()`…) give the
types of the attributes, and the `Includes` and `Fields` rules give the `include` and `fields[type]` parameters.

```php
namespace App\Blog;

use Ark4ne\JsonApi\Requests\Rules\Fields;
use Ark4ne\JsonApi\Requests\Rules\Includes;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

final class Author extends Model {}

final class Article extends Model {}

/**
 * @extends JsonApiResource<Author>
 */
final class AuthorResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['name' => $this->string()];
    }
}

/**
 * @extends JsonApiResource<Article>
 */
final class ArticleResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => $this->string(),
            'published_at' => $this->date(),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return ['author' => $this->one(AuthorResource::class)];
    }
}

final class ShowArticleRequest extends FormRequest
{
    public function rules(): array
    {
        return ['include' => [new Includes(ArticleResource::class)], 'fields' => [new Fields(ArticleResource::class)]];
    }
}

final class ArticleController
{
    /**
     * Show one article.
     */
    public function show(ShowArticleRequest $request, Article $article): ArticleResource
    {
        return new ArticleResource($article);
    }
}

Route::get('api/articles/{article}', [ArticleController::class, 'show'])->name('articles.show');
```

## Checking the documentation in the test suite

Fail the build when the documentation degrades: `--strict` fails on the first warning, `--dry-run` writes nothing.

```php skip
public function test_the_api_documentation_is_complete(): void
{
    $this->artisan('openapi:generate', ['--strict' => true, '--dry-run' => true])->assertSuccessful();
}
```
