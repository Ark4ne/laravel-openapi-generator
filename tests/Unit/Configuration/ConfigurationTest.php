<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Configuration;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\RouteMasks;
use Ark4ne\OpenApi\Configuration\RouteOrder;
use Ark4ne\OpenApi\Configuration\SamplingConfiguration;
use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use Ark4ne\OpenApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The parts of the configuration the epics done so far read; the whole loading comes with E10.
 */
#[CoversClass(Configuration::class)]
#[CoversClass(SamplingConfiguration::class)]
#[CoversClass(\Ark4ne\OpenApi\Configuration\DocumentConfiguration::class)]
final class ConfigurationTest extends TestCase
{
    public function test_the_published_configuration_is_read(): void
    {
        $config = require dirname(__DIR__, 3) . '/config/openapi.php';
        self::assertIsArray($config);

        $configuration = Configuration::fromArray($config);
        $document = $configuration->document('v1');

        self::assertSame(['v1'], array_keys($configuration->documents));
        self::assertEquals(new RouteMasks(uri: ['api/*']), $document->routes->include);
        self::assertSame('doc', $document->summary->by);
        self::assertSame('controller', $document->tags->by);
        self::assertNotNull($document->tags->pattern());
        self::assertNull($document->groups);
        self::assertSame('name', $document->operationId->by);
        self::assertTrue($configuration->exceptions->readThrows);
        self::assertSame(2, $configuration->sampling->collectionSize);
        self::assertSame('date', $configuration->dateFormats['Y-m-d'] ?? null);
    }

    public function test_the_sampling_entry_is_read(): void
    {
        $sampling = Configuration::fromArray(['sampling' => ['transactions' => false, 'connections' => ['mysql'], 'collection_size' => 3, 'seed' => 7, 'examples' => false]])->sampling;

        self::assertEquals(new SamplingConfiguration(false, ['mysql'], 3, 7, false), $sampling);
    }

    public function test_an_invalid_sampling_entry_is_rejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('openapi.sampling.collection_size: expected an integer of at least 1, got 0');

        Configuration::fromArray(['sampling' => ['collection_size' => 0]]);
    }

    public function test_the_routes_the_strategies_and_the_exceptions_are_read(): void
    {
        $configuration = Configuration::fromArray([
            'documents' => [
                'admin' => [
                    'routes' => ['include' => ['admin/*'], 'order' => 'path'],
                    'strategies' => ['summary' => ['by' => 'name'], 'groups' => ['by' => 'uri'], 'operation_id' => ['by' => 'action']],
                ],
            ],
            'exceptions' => ['read_throws' => false],
        ]);
        $document = $configuration->document('admin');

        self::assertSame('admin', $document->name);
        self::assertEquals(new RouteMasks(uri: ['admin/*']), $document->routes->include);
        self::assertSame(RouteOrder::Path, $document->routes->order);
        self::assertSame('name', $document->summary->by);
        self::assertSame('controller', $document->tags->by);
        self::assertSame('uri', $document->groups?->by);
        self::assertSame('action', $document->operationId->by);
        self::assertFalse($configuration->exceptions->readThrows);
    }

    public function test_an_unknown_document_is_rejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('openapi.documents: expected one of v1, got "v2"');

        Configuration::fromArray(['documents' => ['v1' => []]])->document('v2');
    }

    public function test_an_invalid_entry_is_rejected_with_its_key(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('openapi.documents.v1.routes.order');

        Configuration::fromArray(['documents' => ['v1' => ['routes' => ['order' => 'random']]]]);
    }
}
