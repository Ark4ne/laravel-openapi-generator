<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Discovery;

use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Discovery\PathParameterResolver;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers\BindingController;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers\BrokenController;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Priority;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Status;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Broken;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Country;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Order;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Post;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Ticket;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\User;
use Ark4ne\OpenApi\Tests\TestCase;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(PathParameterResolver::class)]
final class PathParameterResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{callable(Route): mixed, array<string, mixed>}>
     */
    public static function constraints(): iterable
    {
        yield 'no constraint' => [static fn(Route $route) => $route, ['type' => 'string']];
        yield 'whereNumber' => [static fn(Route $route) => $route->whereNumber('id'), ['type' => 'integer']];
        yield 'digits' => [static fn(Route $route) => $route->where('id', '\d+'), ['type' => 'integer']];
        yield 'whereAlpha' => [static fn(Route $route) => $route->whereAlpha('id'), ['type' => 'string', 'pattern' => '^[a-zA-Z]+$']];
        yield 'whereAlphaNumeric' => [static fn(Route $route) => $route->whereAlphaNumeric('id'), ['type' => 'string', 'pattern' => '^[a-zA-Z0-9]+$']];
        yield 'whereUuid' => [static fn(Route $route) => $route->whereUuid('id'), ['type' => 'string', 'format' => 'uuid']];
        yield 'whereUlid' => [static fn(Route $route) => $route->whereUlid('id'), ['type' => 'string', 'pattern' => '^[0-7][0-9a-hjkmnp-tv-zA-HJKMNP-TV-Z]{25}$']];
        yield 'whereIn' => [static fn(Route $route) => $route->whereIn('id', ['draft', 'published']), ['type' => 'string', 'enum' => ['draft', 'published']]];
        yield 'whereIn with an enum' => [static fn(Route $route) => $route->whereIn('id', Status::cases()), ['type' => 'string', 'enum' => ['draft', 'published']]];
        yield 'whereIn with leading zeros' => [static fn(Route $route) => $route->whereIn('id', ['01', '10']), ['type' => 'string', 'enum' => ['01', '10']]];
        yield 'whereIn with numbers' => [static fn(Route $route) => $route->whereIn('id', [1, 2]), ['type' => 'integer', 'enum' => [1, 2]]];
        yield 'free expression' => [static fn(Route $route) => $route->where('id', '[a-z]{2}-[0-9]+'), ['type' => 'string', 'pattern' => '^[a-z]{2}-[0-9]+$']];
        yield 'free alternation' => [static fn(Route $route) => $route->where('id', 'v[0-9]+|latest'), ['type' => 'string', 'pattern' => '^(?:v[0-9]+|latest)$']];
    }

    /**
     * @param callable(Route): mixed $constrain
     * @param array<string, mixed> $schema
     */
    #[DataProvider('constraints')]
    public function test_a_where_constraint_gives_the_schema(callable $constrain, array $schema): void
    {
        $route = $this->router()->get('api/items/{id}', static fn($id) => null);
        $constrain($route);

        $parameters = $this->resolve($route, ['id']);

        self::assertSchema($schema, $parameters[0]->schema);
        self::assertTrue($parameters[0]->required);
        self::assertNull($parameters[0]->boundTo);
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>, class-string}>
     */
    public static function bindings(): iterable
    {
        yield 'incrementing key' => ['user', 'users/{user}', ['type' => 'integer'], User::class];
        yield 'string key' => ['country', 'countries/{country}', ['type' => 'string'], Country::class];
        yield 'route key name' => ['post', 'posts/{post}', ['type' => 'string'], Post::class];
        yield 'custom key with a cast' => ['post', 'posts/{post:position}', ['type' => 'integer'], Post::class];
        yield 'custom key with a float cast' => ['post', 'posts/{post:rating}', ['type' => 'number'], Post::class];
        yield 'custom key with a boolean cast' => ['post', 'posts/{post:featured}', ['type' => 'boolean'], Post::class];
        yield 'custom key with an enum cast' => ['post', 'posts/{post:status}', ['type' => 'string', 'enum' => ['draft', 'published']], Post::class];
        yield 'custom key without cast' => ['user', 'users/{user:email}', ['type' => 'string'], User::class];
        yield 'unique id that is not the key' => ['order', 'orders/{order:reference}', ['type' => 'string', 'format' => 'uuid'], Order::class];
        yield 'uuid key' => ['order', 'orders/{order}', ['type' => 'string', 'format' => 'uuid'], Order::class];
        yield 'ulid key' => ['ticket', 'tickets/{ticket}', ['type' => 'string', 'pattern' => '^[0-7][0-9a-hjkmnp-tv-zA-HJKMNP-TV-Z]{25}$'], Ticket::class];
        yield 'string backed enum' => ['status', 'posts/{status}', ['type' => 'string', 'enum' => ['draft', 'published']], Status::class];
        yield 'int backed enum' => ['priority', 'tasks/{priority}', ['type' => 'integer', 'enum' => [1, 2]], Priority::class];
    }

    /**
     * @param array<string, mixed> $schema
     * @param class-string $boundTo
     */
    #[DataProvider('bindings')]
    public function test_a_bound_parameter_gets_the_type_of_its_key(string $action, string $uri, array $schema, string $boundTo): void
    {
        $route = $this->router()->get("api/{$uri}", [BindingController::class, $action]);

        $bound = array_key_last($route->bindingFields());
        $parameters = $this->resolve($route, [is_string($bound) ? $bound : $action]);

        self::assertSchema($schema, $parameters[0]->schema);
        self::assertSame($boundTo, $parameters[0]->boundTo);
    }

    public function test_a_model_that_cannot_be_instantiated_gives_a_string(): void
    {
        $route = $this->router()->get('api/broken/{broken}', [BrokenController::class, 'show']);

        $parameter = $this->resolve($route, ['broken'])[0];

        self::assertSchema(['type' => 'string'], $parameter->schema);
        self::assertSame(Broken::class, $parameter->boundTo);
    }

    public function test_a_closure_binding_is_read(): void
    {
        $route = $this->router()->get('api/users/{user}', static fn(User $user) => null);

        $parameters = $this->resolve($route, ['user']);

        self::assertSchema(['type' => 'integer'], $parameters[0]->schema);
        self::assertSame(User::class, $parameters[0]->boundTo);
    }

    public function test_a_camel_case_argument_binds_a_snake_case_parameter(): void
    {
        $route = $this->router()->get('api/authors/{blog_author}', [BindingController::class, 'snake']);

        self::assertSame(User::class, $this->resolve($route, ['blog_author'])[0]->boundTo);
    }

    public function test_a_where_constraint_refines_a_bound_model(): void
    {
        $route = $this->router()->get('api/posts/{post}', [BindingController::class, 'post'])->whereNumber('post');

        $parameter = $this->resolve($route, ['post'])[0];

        self::assertSchema(['type' => 'integer'], $parameter->schema);
        self::assertSame(Post::class, $parameter->boundTo);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function digitConstraints(): iterable
    {
        yield 'bounded digits' => ['user', '[0-9]{1,6}'];
        yield 'digits shorthand' => ['user', '\d{4}'];
        yield 'no leading zero' => ['user', '[1-9][0-9]*'];
        yield 'int argument' => ['scalar', '[0-9]{1,3}'];
    }

    #[DataProvider('digitConstraints')]
    public function test_a_digit_constraint_keeps_an_integer_type(string $action, string $expression): void
    {
        $parameter = $action === 'user' ? 'user' : 'page';
        $route = $this->router()->get("api/x/{{$parameter}}", [BindingController::class, $action])->where($parameter, $expression);

        self::assertSchema(['type' => 'integer'], $this->resolve($route, [$parameter])[0]->schema);
    }

    public function test_a_digit_constraint_on_a_string_stays_a_pattern(): void
    {
        $route = $this->router()->get('api/x/{code}', static fn(string $code) => null)->where('code', '[0-9]{4}');

        self::assertSchema(['type' => 'string', 'pattern' => '^[0-9]{4}$'], $this->resolve($route, ['code'])[0]->schema);
    }

    public function test_a_where_constraint_does_not_replace_the_values_of_an_enum(): void
    {
        $route = $this->router()->get('api/posts/{status}', [BindingController::class, 'status'])->whereAlpha('status');

        self::assertSchema(['type' => 'string', 'enum' => ['draft', 'published']], $this->resolve($route, ['status'])[0]->schema);
    }

    public function test_scalar_arguments_give_their_type(): void
    {
        $route = $this->router()->get('api/{page}/{ratio}/{slug}/{other}', [BindingController::class, 'scalar']);

        $schemas = array_map(static fn(ParameterDescription $parameter): array => $parameter->schema->toArray(), $this->resolve($route, ['page', 'ratio', 'slug', 'other']));

        self::assertSame([['type' => 'integer'], ['type' => 'number'], ['type' => 'string'], ['type' => 'string']], $schemas);
    }

    public function test_the_parameters_keep_the_given_order_and_names(): void
    {
        $route = $this->router()->get('api/{b}/{a}', [BindingController::class, 'untyped']);

        self::assertSame(['b', 'a'], array_map(static fn(ParameterDescription $parameter): string => $parameter->name, $this->resolve($route, ['b', 'a'])));
    }

    public function test_an_action_that_cannot_be_reflected_gives_strings_and_a_warning(): void
    {
        $route = $this->router()->get('api/users/{user}', 'App\Http\Controllers\MissingController@show');
        $context = $this->context();

        $parameters = (new PathParameterResolver())->resolve($route, ['user'], $context, 'GET /api/users/{user}');

        self::assertSchema(['type' => 'string'], $parameters[0]->schema);
        self::assertDiagnostics(['[warning] discovery GET /api/users/{user}: cannot reflect the action App\Http\Controllers\MissingController@show, path parameters are written as strings'], $context);
    }

    /**
     * @param list<string> $names
     *
     * @return list<ParameterDescription>
     */
    private function resolve(Route $route, array $names): array
    {
        $context = $this->context();
        $parameters = (new PathParameterResolver())->resolve($route, $names, $context, 'GET /test');

        self::assertDiagnostics([], $context);

        return $parameters;
    }
}
