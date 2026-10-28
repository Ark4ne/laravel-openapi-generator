<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Discovery;

use Ark4ne\OpenApi\Attributes\Exclude;
use Ark4ne\OpenApi\Configuration\RouteFilterConfiguration;
use Ark4ne\OpenApi\Configuration\RouteMasks;
use Ark4ne\OpenApi\Configuration\RouteOrder;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Discovery\MiddlewareResolver;
use Ark4ne\OpenApi\Discovery\PathParameterResolver;
use Ark4ne\OpenApi\Discovery\RouteCollector;
use Ark4ne\OpenApi\Discovery\RouteDefinition;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers\BindingController;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers\ExcludedController;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers\InvokableController;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers\UserController;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\User;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Ark4ne\OpenApiWriter\Enums\Strictness;
use Ark4ne\OpenApiWriter\Enums\Version;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RouteCollector::class)]
final class RouteCollectorTest extends TestCase
{
    public function test_the_routes_are_collected_in_registration_order(): void
    {
        $this->router()->post('api/users', [UserController::class, 'store']);
        $this->router()->get('api/users', [UserController::class, 'index']);

        self::assertSame(['POST /api/users', 'GET /api/users'], $this->labels());
    }

    public function test_head_is_ignored_by_default(): void
    {
        $this->router()->get('api/users', [UserController::class, 'index']);

        self::assertSame(['GET /api/users'], $this->labels(new RouteFilterConfiguration(include: new RouteMasks())));
    }

    public function test_any_and_match_give_one_definition_per_method(): void
    {
        $this->router()->match(['PUT', 'PATCH'], 'api/users/{id}', [UserController::class, 'update']);
        $this->router()->any('api/ping', static fn() => null);

        // Router::$verbs gained QUERY during Laravel 13.
        $any = array_map(static fn(string $verb): string => "{$verb} /api/ping", array_values(array_diff(Router::$verbs, ['HEAD'])));

        self::assertSame(['PUT /api/users/{id}', 'PATCH /api/users/{id}', ...$any], $this->labels(version: Version::V3_2_0));
    }

    public function test_an_optional_parameter_gives_a_path_without_and_a_path_with_it(): void
    {
        $this->router()->get('api/posts/{category?}/{page?}', static fn(?string $category = null, ?int $page = null) => null);

        $definitions = $this->collect();

        self::assertSame(['GET /api/posts', 'GET /api/posts/{category}', 'GET /api/posts/{category}/{page}'], array_map(static fn(RouteDefinition $d): string => $d->label(), $definitions));
        self::assertSame([[], ['category'], ['category', 'page']], array_map(self::parameterNames(...), $definitions));
        self::assertSchema(['type' => 'integer'], $definitions[2]->parameters[1]->schema);
    }

    public function test_a_custom_binding_key_is_removed_from_the_path(): void
    {
        $this->router()->get('api/users/{user:email}', [BindingController::class, 'user']);

        $definition = $this->collect()[0];

        self::assertSame('/api/users/{user}', $definition->path);
        self::assertSame(User::class, $definition->parameters[0]->boundTo);
    }

    public function test_the_root_uri_is_the_root_path(): void
    {
        $this->router()->get('/', static fn() => null);

        self::assertSame(['GET /'], $this->labels());
    }

    public function test_the_definition_carries_what_the_router_knows(): void
    {
        $this->router()->aliasMiddleware('auth', Authenticate::class);
        $route = $this->router()->get('api/users/{user}', [UserController::class, 'show'])
            ->name('users.show')
            ->middleware('auth:sanctum')
            ->withTrashed()
            ->scopeBindings();
        $route->domain('{account}.example.com');

        $definition = $this->collect()[0];

        self::assertSame(HttpMethod::Get, $definition->method);
        self::assertSame('users.show', $definition->name);
        self::assertSame('{account}.example.com', $definition->domain);
        self::assertSame(UserController::class, $definition->controller);
        self::assertSame('show', $definition->action);
        self::assertSame(['user'], self::parameterNames($definition));
        self::assertSame(['account'], array_map(static fn(ParameterDescription $p): string => $p->name, $definition->domainParameters));
        self::assertSame(['auth:sanctum'], $definition->middlewares);
        self::assertSame([Authenticate::class . ':sanctum'], $definition->middlewareClasses);
        self::assertTrue($definition->withTrashed);
        self::assertTrue($definition->scopeBindings);
    }

    public function test_the_actions_are_resolved(): void
    {
        $this->router()->get('api/invokable', InvokableController::class);
        $this->router()->group(['controller' => UserController::class], function (): void {
            $this->router()->get('api/grouped', 'index');
        });
        $this->router()->get('api/closure', static fn() => null);

        $actions = array_map(static fn(RouteDefinition $d): array => [$d->controller, $d->action, $d->isClosure()], $this->collect());

        self::assertSame([[InvokableController::class, '__invoke', false], [UserController::class, 'index', false], [null, null, true]], $actions);
    }

    public function test_a_resource_gives_its_operations(): void
    {
        $this->router()->apiResource('api/users', UserController::class);

        self::assertSame(
            ['GET /api/users', 'POST /api/users', 'GET /api/users/{user}', 'PUT /api/users/{user}', 'PATCH /api/users/{user}', 'DELETE /api/users/{user}'],
            $this->labels(),
        );
    }

    public function test_exclude_leaves_out_a_controller_a_method_or_a_closure(): void
    {
        $this->router()->get('api/excluded', [ExcludedController::class, 'index']);
        $this->router()->get('api/hidden', [UserController::class, 'hidden']);
        $this->router()->get('api/closure', #[Exclude] static fn() => null);
        $this->router()->get('api/users', [UserController::class, 'index']);

        self::assertSame(['GET /api/users'], $this->labels());
    }

    public function test_a_route_to_a_missing_controller_or_method_is_kept(): void
    {
        $this->router()->get('api/missing', 'App\Http\Controllers\MissingController@index');
        $this->router()->get('api/unknown', [UserController::class, 'unknown']);

        self::assertSame(['GET /api/missing', 'GET /api/unknown'], $this->labels());
    }

    public function test_the_filters_of_the_document_apply(): void
    {
        $this->router()->get('api/users', [UserController::class, 'index']);
        $this->router()->get('admin/users', [UserController::class, 'index']);
        $this->router()->get('up', static fn() => null);

        self::assertSame(['GET /api/users'], $this->labels(new RouteFilterConfiguration()));
    }

    public function test_query_is_dropped_with_a_warning_before_3_2_in_lenient_mode(): void
    {
        $this->router()->match(['GET', 'QUERY'], 'api/search', static fn() => null);
        $context = $this->context(version: Version::V3_1_2, strictness: Strictness::Lenient);

        self::assertSame(['GET /api/search'], $this->labels(context: $context));
        self::assertDiagnostics(['[warning] discovery QUERY /api/search: the QUERY method is written from OpenAPI 3.2, operation left out'], $context);
    }

    public function test_query_is_kept_before_3_2_in_strict_mode_for_the_writer_to_reject(): void
    {
        $this->router()->match(['QUERY'], 'api/search', static fn() => null);
        $context = $this->context(version: Version::V3_0_4);

        self::assertSame(['QUERY /api/search'], $this->labels(context: $context));
        self::assertDiagnostics([], $context);
    }

    public function test_query_is_a_fixed_method_from_3_2(): void
    {
        $this->router()->match(['QUERY'], 'api/search', static fn() => null);

        self::assertSame(HttpMethod::Query, $this->collect(version: Version::V3_2_0)[0]->method);
    }

    public function test_another_method_is_kept_in_upper_case_from_3_2(): void
    {
        $this->router()->match(['PURGE'], 'api/cache', static fn() => null);

        self::assertSame('PURGE', $this->collect(version: Version::V3_2_0)[0]->method);
    }

    public function test_another_method_is_dropped_with_a_warning_before_3_2_in_lenient_mode(): void
    {
        $this->router()->match(['PURGE', 'DELETE'], 'api/cache', static fn() => null);
        $context = $this->context(version: Version::V3_1_2, strictness: Strictness::Lenient);

        self::assertSame(['DELETE /api/cache'], $this->labels(context: $context));
        self::assertDiagnostics(['[warning] discovery PURGE /api/cache: the PURGE method is written in additionalOperations from OpenAPI 3.2, operation left out'], $context);
    }

    public function test_the_definitions_can_be_sorted_by_path(): void
    {
        $this->router()->get('api/users', [UserController::class, 'index']);
        $this->router()->get('api/comments', static fn() => null);
        $this->router()->post('api/users', [UserController::class, 'store']);
        $this->router()->delete('api/comments', static fn() => null);

        self::assertSame(
            ['GET /api/comments', 'DELETE /api/comments', 'GET /api/users', 'POST /api/users'],
            $this->labels(new RouteFilterConfiguration(include: new RouteMasks(), order: RouteOrder::Path)),
        );
    }

    /**
     * @return list<string>
     */
    private function labels(
        ?RouteFilterConfiguration $routes = null,
        Version $version = Version::V3_1_2,
        ?GenerationContext $context = null,
    ): array {
        return array_map(static fn(RouteDefinition $definition): string => $definition->label(), $this->collect($routes, $version, $context));
    }

    /**
     * @return list<RouteDefinition>
     */
    private function collect(
        ?RouteFilterConfiguration $routes = null,
        Version $version = Version::V3_1_2,
        ?GenerationContext $context = null,
    ): array {
        $router = $this->router();
        $context ??= $this->context(routes: $routes ?? new RouteFilterConfiguration(include: new RouteMasks()), version: $version);

        return (new RouteCollector($router, new MiddlewareResolver($router), new PathParameterResolver()))->collect($context);
    }

    /**
     * @return list<string>
     */
    private static function parameterNames(RouteDefinition $definition): array
    {
        return array_map(static fn(ParameterDescription $parameter): string => $parameter->name, $definition->parameters);
    }
}
