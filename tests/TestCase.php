<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Configuration\ExceptionsConfiguration;
use Ark4ne\OpenApi\Configuration\InfoConfiguration;
use Ark4ne\OpenApi\Configuration\RouteFilterConfiguration;
use Ark4ne\OpenApi\Configuration\RouteMasks;
use Ark4ne\OpenApi\Context\Diagnostic;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Discovery\MiddlewareResolver;
use Ark4ne\OpenApi\Discovery\PathParameterResolver;
use Ark4ne\OpenApi\Discovery\RouteCollector;
use Ark4ne\OpenApi\Discovery\RouteDefinition;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\OperationContextFactory;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Types\TypeResolver;
use Ark4ne\OpenApiWriter\Enums\Strictness;
use Ark4ne\OpenApiWriter\Enums\Version;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase as Testbench;

/**
 * Base of the tests that need a Laravel application, with the shared builders and assertions.
 */
abstract class TestCase extends Testbench
{
    protected function router(): Router
    {
        $router = $this->app?->make(Router::class);
        self::assertInstanceOf(Router::class, $router);

        return $router;
    }

    protected function context(
        RouteFilterConfiguration $routes = new RouteFilterConfiguration(include: new RouteMasks()),
        Version $version = Version::V3_1_2,
        Strictness $strictness = Strictness::Strict,
        ExceptionsConfiguration $exceptions = new ExceptionsConfiguration(),
        ?string $locale = null,
    ): GenerationContext {
        $document = new DocumentConfiguration(
            'v1',
            new InfoConfiguration('API', '1.0.0'),
            version: $version,
            strictness: $strictness,
            routes: $routes,
        );

        return GenerationContext::for(new Configuration(['v1' => $document], exceptions: $exceptions), $document, $locale);
    }

    /**
     * The definitions of the registered routes, every route kept.
     *
     * @return list<RouteDefinition>
     */
    protected function definitions(?GenerationContext $context = null): array
    {
        $router = $this->router();

        return (new RouteCollector($router, new MiddlewareResolver($router), new PathParameterResolver()))->collect($context ?? $this->context());
    }

    /**
     * The operation context of the first definition of a route registered for the test.
     */
    protected function operation(?GenerationContext $context = null): OperationContext
    {
        $context ??= $this->context();
        $definitions = $this->definitions($context);
        self::assertNotEmpty($definitions);

        return (new OperationContextFactory(new TypeResolver()))->create($definitions[0], $context);
    }

    /**
     * Compares a schema with its array form, whatever the order of the keys, at every level.
     *
     * @param array<string, mixed> $expected
     */
    protected static function assertSchema(array $expected, Schema $schema): void
    {
        Schemas::assertSame($expected, $schema);
    }

    /**
     * @param list<string> $expected `[warning] discovery GET /users: …`
     */
    protected static function assertDiagnostics(array $expected, GenerationContext $context): void
    {
        self::assertSame($expected, array_map(static fn(Diagnostic $diagnostic): string => (string) $diagnostic, $context->diagnostics->all()));
    }
}
