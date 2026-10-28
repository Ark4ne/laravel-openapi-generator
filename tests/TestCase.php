<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Configuration\InfoConfiguration;
use Ark4ne\OpenApi\Configuration\RouteFilterConfiguration;
use Ark4ne\OpenApi\Configuration\RouteMasks;
use Ark4ne\OpenApi\Context\Diagnostic;
use Ark4ne\OpenApi\Context\GenerationContext;
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
    ): GenerationContext {
        $document = new DocumentConfiguration(
            'v1',
            new InfoConfiguration('API', '1.0.0'),
            version: $version,
            strictness: $strictness,
            routes: $routes,
        );

        return GenerationContext::for(new Configuration(['v1' => $document]), $document);
    }

    /**
     * Compares a schema with its array form, whatever the order of the keys (the writer follows the specification).
     *
     * @param array<string, mixed> $expected
     */
    protected static function assertSchema(array $expected, Schema $schema): void
    {
        $actual = $schema->toArray();
        ksort($expected);
        ksort($actual);

        self::assertSame($expected, $actual);
    }

    /**
     * @param list<string> $expected `[warning] discovery GET /users: …`
     */
    protected static function assertDiagnostics(array $expected, GenerationContext $context): void
    {
        self::assertSame($expected, array_map(static fn(Diagnostic $diagnostic): string => (string) $diagnostic, $context->diagnostics->all()));
    }
}
