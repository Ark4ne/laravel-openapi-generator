<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Discovery;

use Ark4ne\OpenApi\Configuration\RouteFilterConfiguration;
use Ark4ne\OpenApi\Configuration\RouteMasks;
use Ark4ne\OpenApi\Discovery\RouteFilter;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers\UserController;
use Ark4ne\OpenApi\Tests\TestCase;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(RouteFilter::class)]
final class RouteFilterTest extends TestCase
{
    /**
     * @return iterable<string, array{RouteMasks, RouteMasks, bool}>
     */
    public static function masks(): iterable
    {
        yield 'no include keeps every route' => [new RouteMasks(), new RouteMasks(), true];
        yield 'uri included' => [new RouteMasks(uri: ['api/*']), new RouteMasks(), true];
        yield 'uri mask with a leading slash' => [new RouteMasks(uri: ['/api/users']), new RouteMasks(), true];
        yield 'uri not included' => [new RouteMasks(uri: ['admin/*']), new RouteMasks(), false];
        yield 'name included' => [new RouteMasks(name: ['api.users.*']), new RouteMasks(), true];
        yield 'domain included' => [new RouteMasks(domain: ['*.example.com']), new RouteMasks(), true];
        yield 'declared middleware included' => [new RouteMasks(middleware: ['auth:*']), new RouteMasks(), true];
        yield 'middleware class included' => [new RouteMasks(middleware: ['Illuminate\Auth\Middleware\Authenticate*']), new RouteMasks(), true];
        yield 'middleware class without its arguments' => [new RouteMasks(middleware: ['Illuminate\Auth\Middleware\Authenticate']), new RouteMasks(), true];
        yield 'middleware alias without its arguments' => [new RouteMasks(middleware: ['auth']), new RouteMasks(), true];
        yield 'one matching kind is enough' => [new RouteMasks(uri: ['admin/*'], name: ['api.*']), new RouteMasks(), true];
        yield 'uri excluded' => [new RouteMasks(), new RouteMasks(uri: ['api/users']), false];
        yield 'name excluded' => [new RouteMasks(), new RouteMasks(name: ['api.users.index']), false];
        yield 'domain excluded' => [new RouteMasks(), new RouteMasks(domain: ['{account}.example.com']), false];
        yield 'middleware excluded' => [new RouteMasks(), new RouteMasks(middleware: ['auth:sanctum']), false];
        yield 'exclude wins over include' => [new RouteMasks(uri: ['api/*']), new RouteMasks(uri: ['api/users']), false];
    }

    #[DataProvider('masks')]
    public function test_a_route_is_kept_when_it_matches_include_and_not_exclude(RouteMasks $include, RouteMasks $exclude, bool $accepted): void
    {
        $route = $this->router()->get('api/users', [UserController::class, 'index'])->name('api.users.index');
        $route->domain('{account}.example.com');
        $filter = new RouteFilter(new RouteFilterConfiguration(include: $include, exclude: $exclude));

        self::assertSame($accepted, $filter->accepts($route, static fn(): array => ['auth:sanctum', 'Illuminate\Auth\Middleware\Authenticate:sanctum']));
    }

    public function test_the_middlewares_are_resolved_only_when_a_middleware_mask_needs_them(): void
    {
        $route = $this->router()->get('api/users', [UserController::class, 'index']);
        $resolved = 0;
        $middlewares = static function () use (&$resolved): array {
            $resolved++;

            return ['auth:sanctum'];
        };

        (new RouteFilter(new RouteFilterConfiguration(include: new RouteMasks(uri: ['api/*']))))->accepts($route, $middlewares);
        (new RouteFilter(new RouteFilterConfiguration(include: new RouteMasks(uri: ['admin/*'], middleware: ['auth*']))))->accepts($route, $middlewares);
        (new RouteFilter(new RouteFilterConfiguration(include: new RouteMasks(uri: ['api/*']), exclude: new RouteMasks(middleware: ['auth*']))))->accepts($route, $middlewares);
        (new RouteFilter(new RouteFilterConfiguration(include: new RouteMasks(), internal: ['api/*'], exclude: new RouteMasks(middleware: ['auth*']))))->accepts($route, $middlewares);

        self::assertSame(2, $resolved);
    }

    public function test_a_route_without_name_or_domain_matches_no_name_or_domain_mask(): void
    {
        $route = $this->router()->get('api/users', [UserController::class, 'index']);
        $filter = new RouteFilter(new RouteFilterConfiguration(include: new RouteMasks(name: ['*'], domain: ['*'])));

        self::assertFalse($filter->accepts($route));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function internalRoutes(): iterable
    {
        foreach (['up', 'sanctum/csrf-cookie', 'broadcasting/auth', 'storage/{path}', '_ignition/health-check', 'telescope/requests', 'horizon/api/stats', 'pulse', 'livewire/update', '_debugbar/open', '_boost/browser-logs'] as $uri) {
            yield $uri => [$uri];
        }
    }

    #[DataProvider('internalRoutes')]
    public function test_the_internal_routes_are_left_out(string $uri): void
    {
        $route = $this->router()->get($uri, static fn() => null);

        self::assertFalse((new RouteFilter(new RouteFilterConfiguration(include: new RouteMasks())))->accepts($route));
    }

    public function test_an_internal_route_removed_from_the_list_is_kept(): void
    {
        $route = $this->router()->get('up', static fn() => null);

        self::assertTrue((new RouteFilter(new RouteFilterConfiguration(include: new RouteMasks(), internal: [])))->accepts($route));
    }

    /**
     * @return iterable<string, array{callable(Router): Route}>
     */
    public static function routesLeftOut(): iterable
    {
        yield 'redirect' => [static fn(Router $router): Route => $router->redirect('api/old', 'api/new')];
        yield 'permanent redirect' => [static fn(Router $router): Route => $router->permanentRedirect('api/old', 'api/new')];
        yield 'view' => [static fn(Router $router): Route => $router->view('api/welcome', 'welcome')];
        yield 'fallback' => [static fn(Router $router): Route => $router->fallback(static fn() => null)];
    }

    /**
     * @param callable(Router): Route $register
     */
    #[DataProvider('routesLeftOut')]
    public function test_redirects_views_and_the_fallback_route_are_left_out(callable $register): void
    {
        $route = $register($this->router());

        self::assertFalse((new RouteFilter(new RouteFilterConfiguration(include: new RouteMasks(), internal: [])))->accepts($route));
    }

    public function test_the_ignored_methods_are_removed(): void
    {
        $route = $this->router()->match(['GET', 'POST', 'OPTIONS'], 'api/users', static fn() => null);
        $filter = new RouteFilter(new RouteFilterConfiguration(ignoredMethods: ['HEAD', 'OPTIONS']));

        self::assertSame(['GET', 'POST'], $filter->methods($route));
    }

    public function test_the_methods_are_upper_case_and_unique(): void
    {
        $route = new Route(['get', 'GET', 'purge'], 'api/users', static fn() => null);

        self::assertSame(['GET', 'PURGE', 'HEAD'], (new RouteFilter(new RouteFilterConfiguration(ignoredMethods: [])))->methods($route));
    }
}
