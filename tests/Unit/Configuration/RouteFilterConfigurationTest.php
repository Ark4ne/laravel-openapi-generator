<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Configuration;

use Ark4ne\OpenApi\Configuration\RouteFilterConfiguration;
use Ark4ne\OpenApi\Configuration\RouteMasks;
use Ark4ne\OpenApi\Configuration\RouteOrder;
use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteFilterConfiguration::class)]
#[CoversClass(RouteMasks::class)]
final class RouteFilterConfigurationTest extends TestCase
{
    public function test_an_empty_entry_gives_the_defaults(): void
    {
        $configuration = RouteFilterConfiguration::fromArray('documents.v1.routes', []);

        self::assertEquals(new RouteMasks(uri: ['api/*']), $configuration->include);
        self::assertTrue($configuration->exclude->isEmpty());
        self::assertSame(['HEAD'], $configuration->ignoredMethods);
        self::assertSame(RouteFilterConfiguration::INTERNAL, $configuration->internal);
        self::assertSame(RouteOrder::Registration, $configuration->order);
    }

    public function test_a_list_of_masks_is_read_as_uri_masks(): void
    {
        $configuration = RouteFilterConfiguration::fromArray('documents.v1.routes', [
            'include' => ['admin/*', 'api/*'],
            'exclude' => ['api/internal/*'],
        ]);

        self::assertEquals(new RouteMasks(uri: ['admin/*', 'api/*']), $configuration->include);
        self::assertEquals(new RouteMasks(uri: ['api/internal/*']), $configuration->exclude);
    }

    public function test_a_map_of_masks_is_read_by_kind(): void
    {
        $configuration = RouteFilterConfiguration::fromArray('documents.v1.routes', [
            'include' => ['uri' => ['api/*'], 'name' => ['api.*'], 'domain' => '*.example.com', 'middleware' => ['auth:sanctum']],
        ]);

        self::assertEquals(new RouteMasks(['api/*'], ['api.*'], ['*.example.com'], ['auth:sanctum']), $configuration->include);
        self::assertFalse($configuration->include->isEmpty());
    }

    public function test_a_null_include_keeps_every_route(): void
    {
        $configuration = RouteFilterConfiguration::fromArray('documents.v1.routes', ['include' => null]);

        self::assertTrue($configuration->include->isEmpty());
    }

    public function test_the_other_keys_are_read(): void
    {
        $configuration = RouteFilterConfiguration::fromArray('documents.v1.routes', [
            'ignored_methods' => ['head', 'Options'],
            'internal' => ['up'],
            'order' => 'path',
        ]);

        self::assertSame(['HEAD', 'OPTIONS'], $configuration->ignoredMethods);
        self::assertSame(['up'], $configuration->internal);
        self::assertSame(RouteOrder::Path, $configuration->order);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidEntries(): iterable
    {
        yield 'unknown key' => [['includes' => []], 'openapi.documents.v1.routes: expected the keys include, exclude, ignored_methods, internal, order, got "includes"'];
        yield 'include not a list' => [['include' => 'api/*'], 'openapi.documents.v1.routes.include: expected a list of masks or a map of masks by uri, name, domain, middleware, got "api/*"'];
        yield 'unknown mask kind' => [['include' => ['path' => ['api/*']]], 'openapi.documents.v1.routes.include: expected a list of masks or a map of masks by uri, name, domain, middleware, got "path"'];
        yield 'mask not a string' => [['exclude' => ['uri' => [1]]], 'openapi.documents.v1.routes.exclude.uri: expected a list of strings, got array'];
        yield 'methods not strings' => [['ignored_methods' => [true]], 'openapi.documents.v1.routes.ignored_methods: expected a list of strings, got array'];
        yield 'internal not a list' => [['internal' => 'up'], 'openapi.documents.v1.routes.internal: expected a list of strings, got "up"'];
        yield 'unknown order' => [['order' => 'random'], 'openapi.documents.v1.routes.order: expected one of registration, path, got "random"'];
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[DataProvider('invalidEntries')]
    public function test_an_invalid_entry_is_rejected_with_its_key(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        RouteFilterConfiguration::fromArray('documents.v1.routes', $config);
    }
}
