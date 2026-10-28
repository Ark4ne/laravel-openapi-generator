<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Configuration;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\ExtensionsConfiguration;
use Ark4ne\OpenApi\Configuration\InfoConfiguration;
use Ark4ne\OpenApi\Configuration\IntegrationsConfiguration;
use Ark4ne\OpenApi\Configuration\OutputConfiguration;
use Ark4ne\OpenApi\Configuration\OutputFormat;
use Ark4ne\OpenApi\Configuration\RouteMasks;
use Ark4ne\OpenApi\Configuration\RouteOrder;
use Ark4ne\OpenApi\Configuration\SamplingConfiguration;
use Ark4ne\OpenApi\Configuration\ServerConfiguration;
use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use Ark4ne\OpenApi\Generation\Pass;
use Ark4ne\OpenApi\Tests\Fixtures\Extensions\StampPass;
use Ark4ne\OpenApi\Tests\Fixtures\Extensions\TenantMiddlewareMapper;
use Ark4ne\OpenApi\Tests\Fixtures\Extensions\UppercaseRuleMapper;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApiWriter\Enums\Strictness;
use Ark4ne\OpenApiWriter\Enums\VendorExtensions;
use Ark4ne\OpenApiWriter\Enums\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The loading of `config/openapi.php`: every key, and an explicit error for each invalid one.
 */
#[CoversClass(Configuration::class)]
#[CoversClass(SamplingConfiguration::class)]
#[CoversClass(InfoConfiguration::class)]
#[CoversClass(ServerConfiguration::class)]
#[CoversClass(OutputConfiguration::class)]
#[CoversClass(\Ark4ne\OpenApi\Configuration\DocumentConfiguration::class)]
#[CoversClass(ExtensionsConfiguration::class)]
#[CoversClass(IntegrationsConfiguration::class)]
final class ConfigurationTest extends TestCase
{
    public function test_the_published_configuration_is_read(): void
    {
        $config = require dirname(__DIR__, 3) . '/config/openapi.php';
        self::assertIsArray($config);

        $configuration = Configuration::fromArray($config);
        $document = $configuration->document('v1');

        self::assertSame(['v1'], array_keys($configuration->documents));
        self::assertSame('API', $document->info->title);
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

    public function test_what_the_writing_needs_is_read(): void
    {
        $configuration = Configuration::fromArray([
            'servers' => [['url' => 'https://api.example.com', 'description' => 'Production', 'name' => 'prod', 'variables' => ['region' => ['default' => 'eu', 'enum' => ['eu', 'us']]]]],
            'output' => ['disk' => 'local', 'directory' => 'docs', 'file' => '{document}.json', 'format' => 'json'],
            'languages' => ['en', 'fr'],
            'documents' => [
                'v1' => [
                    'openapi' => '3.2.0',
                    'strict' => false,
                    'vendor_extensions' => 'redoc',
                    'info' => ['title' => 'API', 'version' => '1.0.0', 'summary' => 'S', 'description' => 'D', 'terms_of_service' => 'https://example.com/terms', 'contact' => ['name' => 'Team', 'email' => 'team@example.com'], 'license' => ['name' => 'MIT', 'identifier' => 'MIT']],
                    'self' => 'https://example.com/openapi.json',
                    'external_docs' => ['url' => 'https://example.com/docs', 'description' => 'Guide'],
                    'output' => ['file' => 'openapi-{document}.yaml', 'format' => 'yaml'],
                    'extensions' => ['x-logo' => ['url' => 'logo.png']],
                ],
                'v2' => ['info' => ['title' => 'API 2', 'version' => '2.0.0'], 'servers' => [['url' => 'https://v2.example.com']]],
            ],
        ]);
        $v1 = $configuration->document('v1');
        $v2 = $configuration->document('v2');

        self::assertSame([Version::V3_2_0, Strictness::Lenient, VendorExtensions::Redoc], [$v1->version, $v1->strictness, $v1->vendorExtensions]);
        self::assertEquals(new InfoConfiguration('API', '1.0.0', 'S', 'D', 'https://example.com/terms', ['name' => 'Team', 'email' => 'team@example.com'], ['name' => 'MIT', 'identifier' => 'MIT']), $v1->info);
        self::assertSame('https://example.com/openapi.json', $v1->self);
        self::assertSame(['url' => 'https://example.com/docs', 'description' => 'Guide'], $v1->externalDocs);
        self::assertEquals(new OutputConfiguration('local', 'docs', 'openapi-{document}.yaml', OutputFormat::Yaml), $v1->output);
        self::assertSame(['x-logo' => ['url' => 'logo.png']], $v1->extensions);
        self::assertEquals([new ServerConfiguration('https://api.example.com', 'Production', ['region' => ['default' => 'eu', 'enum' => ['eu', 'us']]], 'prod')], $v1->servers);
        self::assertEquals([new ServerConfiguration('https://v2.example.com')], $v2->servers);
        self::assertSame([Version::V3_1_2, Strictness::Strict, VendorExtensions::None], [$v2->version, $v2->strictness, $v2->vendorExtensions]);
        self::assertEquals(new OutputConfiguration('local', 'docs', '{document}.json', OutputFormat::Json), $v2->output);
        self::assertSame(['en', 'fr'], $configuration->languages);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidWriting(): iterable
    {
        yield 'unknown version' => [['documents' => ['v1' => ['openapi' => '3.3']]], 'openapi.documents.v1.openapi: expected one of 3.0.0, 3.0.1, 3.0.2, 3.0.3, 3.0.4, 3.1.0, 3.1.1, 3.1.2, 3.2.0, got "3.3"'];
        yield 'unknown vendor extensions' => [['documents' => ['v1' => ['vendor_extensions' => 'swagger']]], 'openapi.documents.v1.vendor_extensions: expected one of none, redoc, got "swagger"'];
        yield 'title missing' => [['documents' => ['v1' => ['info' => ['version' => '1']]]], 'openapi.documents.v1.info.title: expected a string, got null'];
        yield 'server without url' => [['servers' => [['description' => 'x']]], 'openapi.servers.0.url: expected a string, got null'];
        yield 'unknown format' => [['output' => ['format' => 'xml']], 'openapi.output.format: expected one of json, yaml, got "xml"'];
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[DataProvider('invalidWriting')]
    public function test_an_invalid_writing_entry_is_rejected(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        Configuration::fromArray($config + ['documents' => ['v1' => []]]);
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

    public function test_the_middlewares_the_extensions_and_the_integrations_are_read(): void
    {
        $configuration = Configuration::fromArray([
            'middlewares' => ['tenant' => [TenantMiddlewareMapper::class]],
            'extensions' => ['rule_mappers' => [UppercaseRuleMapper::class], 'passes' => [StampPass::class]],
            'integrations' => ['json_api' => ['errors' => true]],
        ]);

        self::assertSame(['tenant' => [TenantMiddlewareMapper::class]], $configuration->middlewares);
        self::assertEquals(new ExtensionsConfiguration(ruleMappers: [UppercaseRuleMapper::class], passes: [StampPass::class]), $configuration->extensions);
        self::assertTrue($configuration->integrations->jsonApiErrors);
        self::assertSame([Pass::class], Configuration::fromArray(['extensions' => ['passes' => [Pass::class]]])->extensions->passes);
        self::assertFalse(Configuration::fromArray([])->integrations->jsonApiErrors);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidEntries(): iterable
    {
        yield 'v0.6 key' => [['versions' => []], 'openapi: expected the keys documents, servers, output, sampling, date_formats, middlewares, extensions, integrations, exceptions, languages, got "versions"'];
        yield 'middleware mappers not a list' => [['middlewares' => ['tenant' => TenantMiddlewareMapper::class]], 'openapi.middlewares.tenant: expected a list of classes'];
        yield 'missing middleware mapper' => [['middlewares' => ['tenant' => ['App\\Missing']]], 'openapi.middlewares.tenant: expected existing classes or interfaces, got "App\\Missing"'];
        yield 'unknown registry' => [['extensions' => ['parsers' => []]], 'openapi.extensions: expected the keys operation_extractors, request_extractors, rule_mappers, response_extractors, samplers, passes, got "parsers"'];
        yield 'missing extension' => [['extensions' => ['passes' => ['App\\Missing']]], 'openapi.extensions.passes: expected existing classes or interfaces, got "App\\Missing"'];
        yield 'unknown integration' => [['integrations' => ['sanctum' => []]], 'openapi.integrations: expected the keys json_api, got "sanctum"'];
        yield 'json api errors' => [['integrations' => ['json_api' => ['errors' => 'yes']]], 'openapi.integrations.json_api.errors: expected a boolean, got "yes"'];
        yield 'use references' => [['documents' => ['v1' => ['use_references' => 'no']]], 'openapi.documents.v1.use_references: expected a boolean, got "no"'];
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[DataProvider('invalidEntries')]
    public function test_an_invalid_entry_is_rejected_with_the_expected_value(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        Configuration::fromArray($config);
    }

    public function test_the_format_of_the_output_can_be_replaced(): void
    {
        $document = Configuration::fromArray(['documents' => ['v1' => []]])->document('v1')->withFormat(OutputFormat::Yaml);

        self::assertSame(OutputFormat::Yaml, $document->output->format);
        self::assertSame('{document}.yaml', $document->output->file);
        self::assertSame('v1', $document->name);
        self::assertSame('docs/{document}.json', Configuration::fromArray(['documents' => ['v1' => ['output' => ['file' => 'docs/{document}.yml', 'format' => 'yaml']]]])->document('v1')->withFormat(OutputFormat::Json)->output->file);
        self::assertSame('{document}', Configuration::fromArray(['documents' => ['v1' => ['output' => ['file' => '{document}']]]])->document('v1')->withFormat(OutputFormat::Yaml)->output->file);
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
