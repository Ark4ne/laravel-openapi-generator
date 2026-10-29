<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Discovery;

use Ark4ne\OpenApi\Discovery\RouteDefinition;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers\UserController;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteDefinition::class)]
final class RouteDefinitionTest extends TestCase
{
    public function test_the_label_is_the_method_and_the_path(): void
    {
        $route = new Route(['GET'], 'users/{user}', static fn() => null);

        self::assertSame('GET /users/{user}', (new RouteDefinition($route, HttpMethod::Get, '/users/{user}'))->label());
        self::assertSame('PURGE /cache', (new RouteDefinition($route, 'PURGE', '/cache'))->label());
    }

    public function test_a_definition_without_controller_is_a_closure(): void
    {
        $route = new Route(['GET'], 'users', static fn() => null);

        self::assertTrue((new RouteDefinition($route, HttpMethod::Get, '/users'))->isClosure());
        self::assertFalse((new RouteDefinition($route, HttpMethod::Get, '/users', controller: UserController::class, action: 'index'))->isClosure());
    }
}
