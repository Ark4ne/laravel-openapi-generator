<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Discovery;

use Ark4ne\OpenApi\Discovery\MiddlewareResolver;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers\BrokenController;
use Ark4ne\OpenApi\Tests\TestCase;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MiddlewareResolver::class)]
final class MiddlewareResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $router = $this->router();
        $router->aliasMiddleware('auth', Authenticate::class);
        $router->aliasMiddleware('throttle', ThrottleRequests::class);
        $router->middlewareGroup('documented', ['throttle:api', SubstituteBindings::class]);
        $router->middlewareGroup('nested', ['documented', 'auth:sanctum']);
    }

    public function test_the_declared_middlewares_keep_their_aliases_and_arguments(): void
    {
        $route = $this->router()->get('api/users', static fn() => null)->middleware(['auth:sanctum', 'throttle:60,1']);

        self::assertSame(['auth:sanctum', 'throttle:60,1'], (new MiddlewareResolver($this->router()))->declared($route));
    }

    public function test_the_groups_are_expanded_recursively(): void
    {
        $route = $this->router()->get('api/users', static fn() => null)->middleware('nested');

        self::assertSame(['throttle:api', SubstituteBindings::class, 'auth:sanctum'], (new MiddlewareResolver($this->router()))->declared($route));
    }

    public function test_the_excluded_middlewares_and_the_duplicates_are_removed(): void
    {
        $route = $this->router()->get('api/users', static fn() => null)
            ->middleware(['documented', 'auth:sanctum', 'auth:sanctum'])
            ->withoutMiddleware('throttle:api');

        self::assertSame([SubstituteBindings::class, 'auth:sanctum'], (new MiddlewareResolver($this->router()))->declared($route));
    }

    public function test_the_classes_are_resolved_by_the_router(): void
    {
        $route = $this->router()->get('api/users', static fn() => null)->middleware(['auth:sanctum', 'documented']);

        $classes = (new MiddlewareResolver($this->router()))->classes($route);

        self::assertContains(Authenticate::class . ':sanctum', $classes);
        self::assertContains(ThrottleRequests::class . ':api', $classes);
        self::assertContains(SubstituteBindings::class, $classes);
        self::assertCount(3, $classes);
    }

    public function test_only_the_route_middlewares_are_read_when_the_controller_cannot_be_built(): void
    {
        $route = $this->router()->get('api/broken/{broken}', [BrokenController::class, 'show'])->middleware('auth:sanctum');
        $resolver = new MiddlewareResolver($this->router());

        self::assertSame(['auth:sanctum'], $resolver->declared($route));
        self::assertSame([], $resolver->classes($route));
    }

    public function test_a_route_without_middleware_has_none(): void
    {
        $route = $this->router()->get('api/users', static fn() => null);
        $resolver = new MiddlewareResolver($this->router());

        self::assertSame([], $resolver->declared($route));
        self::assertSame([], $resolver->classes($route));
    }
}
