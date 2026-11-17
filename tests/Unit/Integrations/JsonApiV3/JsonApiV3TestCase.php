<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApiV3;

use Ark4ne\ApiRevisions\Revisions\RevisionScope;
use Ark4ne\JsonApi\Laravel\JsonApiServiceProvider;
use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Configuration\InfoConfiguration;
use Ark4ne\OpenApi\Configuration\SamplingConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Extraction\Responses\ResourceSchemas;
use Ark4ne\OpenApi\Integrations\JsonApiV3\JsonApiResourceFormat;
use Ark4ne\OpenApi\Integrations\JsonApiV3\ResourceDefinitions;
use Ark4ne\OpenApi\Sampling\ModelSchemas;
use Ark4ne\OpenApi\Sampling\RelationLoader;
use Ark4ne\OpenApi\Sampling\ResourceModels;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Sampling\SchemaInferrer;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\ApiRevision;
use Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApi\JsonApiTestCase;
use Ark4ne\OpenApi\Types\TypeResolver;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * An application of `ark4ne/laravel-json-api` 3.x whose revisions are `ApiRevision`, with the tables of the models
 * of the fixtures; the scope of the base, a process state, back to the head around each test.
 */
abstract class JsonApiV3TestCase extends JsonApiTestCase
{
    private ?GenerationContext $shared = null;

    private ?ResourceSchemas $resources = null;

    protected function setUp(): void
    {
        parent::setUp();

        RevisionScope::get()->setBase(null);
    }

    protected function tearDown(): void
    {
        RevisionScope::get()->setBase(null);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [JsonApiServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('revisions.enum', ApiRevision::class);
    }

    /**
     * One context per test, without examples.
     */
    protected function ctx(): GenerationContext
    {
        return $this->shared ??= $this->context(sampling: new SamplingConfiguration(examples: false));
    }

    /**
     * The resource schemas of the test, the JSON:API format reading `$definitions`.
     */
    protected function resources(ResourceDefinitions $definitions = new ResourceDefinitions()): ResourceSchemas
    {
        $types = new TypeResolver();
        $samplers = Samplers::default($this->samplingSession(), $types);

        return $this->resources ??= new ResourceSchemas(new ResourceModels($types), $samplers, new SchemaInferrer(new ModelSchemas($types)), new RelationLoader($samplers), [new JsonApiResourceFormat($definitions)]);
    }

    /**
     * A context, without examples, whose document gives `$settings` to the integration (`json_api`).
     *
     * @param array<string, mixed> $settings
     */
    protected function documented(array $settings): GenerationContext
    {
        $document = new DocumentConfiguration('v1', new InfoConfiguration('API', '1.0.0'), integrations: $settings === [] ? [] : ['json_api' => $settings]);

        return GenerationContext::for(new Configuration(['v1' => $document], sampling: new SamplingConfiguration(examples: false)), $document);
    }

    /**
     * The diagnostics of the context of the test, one per line.
     */
    protected function diagnostics(): string
    {
        return implode("\n", array_map(strval(...), $this->ctx()->diagnostics->all()));
    }

    protected static function at(Schema $schema, string ...$path): Schema
    {
        foreach ($path as $segment) {
            $next = $schema->properties[$segment] ?? null;
            self::assertInstanceOf(Schema::class, $next, $segment);
            $schema = $next;
        }

        return $schema;
    }
}
