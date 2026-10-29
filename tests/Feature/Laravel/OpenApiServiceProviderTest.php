<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature\Laravel;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use Ark4ne\OpenApi\Generation\Generator;
use Ark4ne\OpenApi\Generation\GeneratorExtensions;
use Ark4ne\OpenApi\Laravel\ExtensionsResolver;
use Ark4ne\OpenApi\Laravel\GeneratorFactory;
use Ark4ne\OpenApi\Laravel\OpenApiServiceProvider;
use Ark4ne\OpenApi\Tests\Fixtures\Extensions\StampOperationExtractor;
use Ark4ne\OpenApi\Tests\Fixtures\Extensions\StampPass;
use Ark4ne\OpenApi\Tests\Fixtures\Extensions\TeapotSampler;
use Ark4ne\OpenApi\Tests\Fixtures\Extensions\UppercaseRuleMapper;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\ResponseController;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(OpenApiServiceProvider::class)]
#[CoversClass(ExtensionsResolver::class)]
#[CoversClass(GeneratorFactory::class)]
final class OpenApiServiceProviderTest extends LaravelTestCase
{
    public function test_the_configuration_is_merged_and_publishable(): void
    {
        self::assertSame('3.1.2', $this->config()->get('openapi.documents.v1.openapi'));
        self::assertSame([dirname(__DIR__, 3) . '/config/openapi.php'], array_map(realpath(...), array_keys(ServiceProvider::pathsToPublish(OpenApiServiceProvider::class, 'openapi-config'))));
    }

    public function test_the_commands_are_registered(): void
    {
        $kernel = $this->app?->make(Kernel::class);
        self::assertInstanceOf(Kernel::class, $kernel);

        self::assertArrayHasKey('openapi:generate', $kernel->all());
        self::assertArrayHasKey('openapi:inspect', $kernel->all());
    }

    public function test_the_extensions_come_from_the_configuration_and_the_container_tags(): void
    {
        $this->config()->set('openapi.extensions.operation_extractors', [StampOperationExtractor::class]);
        $this->config()->set('openapi.extensions.passes', [StampPass::class]);
        $this->app?->tag([TeapotSampler::class], 'openapi.samplers');
        $this->app?->tag([UppercaseRuleMapper::class], 'openapi.rule-mappers');

        $extensions = $this->app?->make(GeneratorExtensions::class);

        self::assertInstanceOf(GeneratorExtensions::class, $extensions);
        self::assertInstanceOf(StampOperationExtractor::class, $extensions->operationExtractors[0] ?? null);
        self::assertInstanceOf(StampPass::class, $extensions->passes[0] ?? null);
        self::assertInstanceOf(TeapotSampler::class, $extensions->samplers[0] ?? null);
        self::assertInstanceOf(UppercaseRuleMapper::class, $extensions->ruleMappers[0] ?? null);
    }

    public function test_an_extension_of_the_wrong_kind_is_rejected_with_its_key(): void
    {
        $this->config()->set('openapi.extensions.passes', [UppercaseRuleMapper::class]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('openapi.extensions.passes: expected classes implementing Ark4ne\OpenApi\Generation\Pass, got "' . UppercaseRuleMapper::class . '"');

        $this->app?->make(GeneratorExtensions::class);
    }

    public function test_a_tagged_service_of_the_wrong_kind_is_rejected_with_its_tag(): void
    {
        $this->app?->tag([UppercaseRuleMapper::class], 'openapi.passes');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('tag openapi.passes: expected services implementing Ark4ne\OpenApi\Generation\Pass, got "' . UppercaseRuleMapper::class . '"');

        $this->app?->make(GeneratorExtensions::class);
    }

    public function test_the_generator_works_from_the_configuration_it_is_given(): void
    {
        $this->config()->set('openapi.documents.v1.routes.include', null);
        $this->router()->get('api/articles/{article}', [ResponseController::class, 'show']);
        $configuration = Configuration::fromArray(['documents' => ['v1' => ['info' => ['title' => 'API', 'version' => '1'], 'routes' => ['include' => null]]], 'extensions' => ['passes' => [StampPass::class]]]);
        $factory = $this->app?->make(GeneratorFactory::class);
        self::assertInstanceOf(GeneratorFactory::class, $factory);

        $operation = $factory->make($configuration)->generate($configuration, 'v1')->description->operations[0];

        self::assertSame(['pass' => true], $operation->extensions);
    }

    #[Group('json-api')]
    public function test_the_generator_uses_the_built_in_integrations(): void
    {
        $this->config()->set('openapi.integrations.json_api.errors', true);
        $this->config()->set('openapi.documents.v1.routes.include', null);
        $this->router()->get('api/articles/{article}', [ResponseController::class, 'show'])->middleware('auth');
        $generator = $this->app?->make(Generator::class);
        self::assertInstanceOf(Generator::class, $generator);
        $config = $this->config()->get('openapi');
        self::assertIsArray($config);

        $description = $generator->generate(Configuration::fromArray($config), 'v1')->description;

        self::assertSame(['application/vnd.api+json'], array_keys($description->responses['Unauthenticated']->contents ?? []));
    }

    public function test_the_about_command_shows_the_package(): void
    {
        $this->command('about')->expectsOutputToContain('OpenAPI Generator')->assertSuccessful();
    }

    private function config(): Repository
    {
        $config = $this->app?->make(Repository::class);
        self::assertInstanceOf(Repository::class, $config);

        return $config;
    }
}
