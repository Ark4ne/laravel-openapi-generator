<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature\Routes;

use Ark4ne\OpenApi\Configuration\RouteFilterConfiguration;
use Ark4ne\OpenApi\Configuration\RouteMasks;
use Ark4ne\OpenApi\Discovery\MiddlewareResolver;
use Ark4ne\OpenApi\Discovery\PathParameterResolver;
use Ark4ne\OpenApi\Discovery\RouteCollector;
use Ark4ne\OpenApi\Discovery\RouteDefinition;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Post;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\User;
use Ark4ne\OpenApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Discovery of the routes of the v0.6 test application. The expected documents come with the writing (E8).
 */
#[CoversNothing]
final class RoutesTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        require dirname(__DIR__, 2) . '/Fixtures/Routing/routes.php';
    }

    public function test_every_operation_of_the_v0_6_application_is_discovered(): void
    {
        $definitions = $this->collect();

        // Same operations as tests/Feature/expected/openapi-jsonresource.json of v0.6, in registration order.
        self::assertSame([
            'GET /user',
            'GET /user/{user}',
            'PUT /user/{id}',
            'PUT /user/{id}/basic-request',
            'PUT /user/{id}/no-request',
            'POST /user',
            'POST /user/basic-request',
            'POST /user/no-request',
            'PATCH /user/{id}',
            'PATCH /user/{id}/basic-request',
            'PATCH /user/{id}/no-request',
            'GET /comment',
            'GET /comment/{comment}',
            'GET /post',
            'GET /post/{post}',
            'DELETE /user/{user}',
            'DELETE /comment/{comment}',
            'DELETE /post/{post}',
        ], array_map(static fn(RouteDefinition $definition): string => $definition->label(), $definitions));
    }

    public function test_the_path_parameters_are_typed_better_than_v0_6(): void
    {
        $definitions = $this->collect();
        $byLabel = array_combine(array_map(static fn(RouteDefinition $d): string => $d->label(), $definitions), $definitions);

        // v0.6 wrote every path parameter as a string.
        self::assertSchema(['type' => 'integer'], $byLabel['GET /user/{user}']->parameters[0]->schema);
        self::assertSame(User::class, $byLabel['GET /user/{user}']->parameters[0]->boundTo);
        self::assertSchema(['type' => 'integer'], $byLabel['PUT /user/{id}']->parameters[0]->schema);
        self::assertSchema(['type' => 'string'], $byLabel['GET /post/{post}']->parameters[0]->schema);
        self::assertSame(Post::class, $byLabel['GET /post/{post}']->parameters[0]->boundTo);
        self::assertSchema(['type' => 'string'], $byLabel['GET /comment/{comment}']->parameters[0]->schema);
        self::assertSame(['auth:sanctum'], $byLabel['DELETE /post/{post}']->middlewares);
    }

    /**
     * @return list<RouteDefinition>
     */
    private function collect(): array
    {
        $router = $this->router();
        $context = $this->context(new RouteFilterConfiguration(include: new RouteMasks()));
        $definitions = (new RouteCollector($router, new MiddlewareResolver($router), new PathParameterResolver()))->collect($context);

        self::assertDiagnostics([], $context);

        return $definitions;
    }
}
