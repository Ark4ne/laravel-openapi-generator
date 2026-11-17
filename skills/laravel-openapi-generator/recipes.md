# Recipes

Each recipe is one complete `php` block: the classes it needs, then its routes. The package's test suite runs every
block as `routes/api.php` of a Laravel application (its routes under the `api` prefix), generates its document with
the published configuration in 3.0, 3.1 and 3.2, and validates it against the official schemas. In an application,
each class goes to its own file (`app/Models/`, `app/Http/…`) and the routes to `routes/api.php`; the namespaces here
only keep the recipes apart. Only the routes matching `routes.include` (`api/*` by default) are documented.

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
     * Remove a book: a `Response` returned by a `DELETE` is a 204.
     */
    public function destroy(Book $book): HttpResponse
    {
        $book->delete();

        return response()->noContent();
    }
}

Route::middleware('auth:sanctum')->group(static function (): void {
    Route::get('books', [BookController::class, 'index'])->name('books.index');
    Route::post('books', [BookController::class, 'store'])->name('books.store');
    Route::get('books/{book}', [BookController::class, 'show'])->name('books.show');
    Route::delete('books/{book}', [BookController::class, 'destroy'])->name('books.destroy');
});
```

## Declared responses and exceptions

`#[Response]` declares what the return type cannot say, one per status; it replaces the response inferred for its
status, and a declared success (2xx) leaves out the successes inferred for other statuses (a `JsonResponse` is
inferred as a 200 whatever status it is built with). On the controller, it is shared by every action, which replaces
it for the same status. Each `@throws` of the action becomes an error
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

#[Response(401, description: 'Unauthenticated')]
final class SeatController
{
    /**
     * Book a seat.
     *
     * @throws SeatTakenException
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    #[Response(201, description: 'Seat booked', type: 'array{seat: string, expires_at: string}', headers: ['Location' => 'string'])]
    public function book(string $seat): JsonResponse
    {
        return new JsonResponse(['seat' => $seat, 'expires_at' => now()->addMinutes(15)->toAtomString()], 201, ['Location' => "/api/seats/{$seat}"]);
    }
}

Route::post('seats/{seat}/booking', [SeatController::class, 'book'])->name('seats.book');
```

## Requests described by hand

`DescribesRequest` completes the rules (headers, cookies, query, other media types); `#[QueryParameter]`, `#[Header]`
and `#[Cookie]` do the same on the controller, the request class or the action, `#[PathParameter]` on the action; a
rule of the application describes itself with `DescribesRule`.

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

Route::get('catalogs/{catalog}/search', [SearchController::class, 'search'])->name('catalogs.search');
```

## Webhooks and callbacks

A webhook is a request the application sends. Describe it on a route whose action reads it as it would be received
(its form request is the payload) and mark it `#[Webhook]`; the route is documented under `webhooks` (3.1+,
`x-webhooks` with `redoc` before), never under `paths`. `#[Callback]` documents a request sent back to a URL the
client gave, reusing a webhook or describing its own payload.

The webhook routes exist only to be documented: like any route, they must match `routes.include` (here under `api/`),
and a middleware must keep them from answering. Do not register them conditionally (`runningInConsole()`): the
console also builds the route cache (`route:cache`), so they would be served anyway.

```php
namespace App\Orders;

use Ark4ne\OpenApi\Attributes\Callback;
use Ark4ne\OpenApi\Attributes\Header;
use Ark4ne\OpenApi\Attributes\Webhook;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Keeps the routes that describe the webhooks from answering.
 */
final class DocumentedOnly
{
    public function handle(Request $request, Closure $next): never
    {
        abort(404);
    }
}

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

Route::middleware(DocumentedOnly::class)->group(static function (): void {
    Route::post('webhooks/orders/paid', [OrderWebhooks::class, 'paid']);
});

Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
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

Route::post('contact', [ContactController::class, 'store'])->name('contact.store');
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

With `ark4ne/laravel-json-api` 1.x installed, its resources are written in `application/vnd.api+json` as resource
objects (`id`, `type`, `attributes`, `relationships`), the descriptors (`$this->string()`, `$this->date()`…) give the
types of the attributes, and the `Includes` and `Fields` rules give the `include` and `fields[type]` parameters. With
2.x or 3.x, see the next recipes.

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

Route::get('articles/{article}', [ArticleController::class, 'show'])->name('articles.show');
```

## JSON:API resources (2.x)

With `ark4ne/laravel-json-api` 2.x installed, the generator reads the definition the package compiles
(`ArticleResource::definition()`): the `ValueType` of each value gives its type, the `related` resources of each
relationship its identifiers and the `included` resources, `id` is a string and the documents carry the `jsonapi`
member. A value without descriptor (a closure) is typed by rendering the resource on a sample of its model. The rules
are those of `Ark4ne\JsonApi\Http\Rules`; `integrations.json_api.errors` writes the error documents of
`JsonApi::renderErrors()`.

```php
namespace App\BlogV2;

use Ark4ne\JsonApi\Http\Rules\Fields;
use Ark4ne\JsonApi\Http\Rules\Includes;
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
            'published_at' => $this->datetime(),
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

Route::get('articles/{article}', [ArticleController::class, 'show'])->name('articles.show');
```

## JSON:API resources (3.x)

With `ark4ne/laravel-json-api` 3.x (built on `ark4ne/laravel-api-revisions`) installed, the generator reads the
definition the package compiles, whose values are the shapes of the base: each field gives its type and its
constraints (`min()`, `max()`, `format()`, `pattern()`), its description, its first example and `deprecated`, under its
name at the revision of the document. An attribute rendered only when the sparse fieldset names it
(`whenInFields()`) says so; the links of a resource object are a URL or a link object by name. A nested resource
(`Schema::resource()`) has the members of its own shape at that revision. A value of unknown type (`mixed()`) and a
custom type are typed by rendering the resource on a sample of its model (an info diagnostic for the last). Each revision of the API is its own document: `json_api.revision`, a case of the enum
the application configures (`revisions.enum`), names it; every request the generator builds is read at that revision
(the base revision of the `RevisionScope`), and the description lists the newer revisions. Without the setting, the
head.

```php
namespace App\BlogV3;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Http\Rules\Fields;
use Ark4ne\JsonApi\Http\Rules\Includes;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
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
#[JsonApiType('authors')]
final class AuthorResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['name' => Schema::string()];
    }
}

/**
 * @extends JsonApiResource<Article>
 */
#[JsonApiType('articles')]
final class ArticleResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => Schema::string()->max(120)->describe('The title.'),
            'rating' => Schema::int()->min(1)->max(5)->nullable(),
            'published_at' => Schema::dateTime(),
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

Route::get('articles/{article}', [ArticleController::class, 'show'])->name('articles.show');
```

## JSON:API revisions (2.1)

With `ark4ne/laravel-json-api` 2.1, each revision of the API is its own document: the `json_api.revision` setting of
the document names it (a case of the application's `Revision` enum), its `routes` choose its endpoints. Its resources,
`include` and `fields` parameters are those of that revision, the rules of its form requests are resolved at it
(`Http\Rules\Revised`, in any form request), and its description lists the newer revisions. A document without the
setting documents the head. A resource that does not exist at the revision is a warning: exclude its routes from that
document.

```php
namespace App\Revisions;

use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Http\Rules\Revised;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

enum ApiRevision: string implements Revision
{
    case Initial = '2026-09-01';
    case Labels = '2026-12-01';

    public function description(): string
    {
        return match ($this) {
            self::Initial => 'First published revision',
            self::Labels => 'Names become labels',
        };
    }
}

final class Tag extends Model {}

/**
 * @extends JsonApiResource<Tag>
 */
final class TagResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['label' => $this->string()->was(ApiRevision::Initial, name: 'name')];
    }
}

final class StoreTagRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => Revised::rules('required|string')->until(ApiRevision::Initial),
            'label' => Revised::rules('required|string')->since(ApiRevision::Labels),
        ];
    }
}

final class TagController
{
    /**
     * Create a tag.
     */
    public function store(StoreTagRequest $request): TagResource
    {
        return new TagResource(new Tag());
    }
}

Route::post('tags', [TagController::class, 'store'])->name('tags.store');
```

```php
// config/openapi.php: one document per revision
'documents' => [
    'v2026-09' => [
        'info' => ['title' => 'API', 'version' => '2026-09-01'],
        'routes' => ['include' => ['api/*']],
        'integrations' => ['json_api' => ['revision' => App\Revisions\ApiRevision::Initial]],
    ],
    'v2026-12' => [
        'info' => ['title' => 'API', 'version' => '2026-12-01'],
        'routes' => ['include' => ['api/*']],
        'integrations' => ['json_api' => ['revision' => App\Revisions\ApiRevision::Labels]],
    ],
],
```

## Checking the documentation in the test suite

Fail the build when the documentation degrades: `--strict` fails on the first warning, `--dry-run` writes nothing.

```php skip
public function test_the_api_documentation_is_complete(): void
{
    $this->artisan('openapi:generate', ['--strict' => true, '--dry-run' => true])->assertSuccessful();
}
```
