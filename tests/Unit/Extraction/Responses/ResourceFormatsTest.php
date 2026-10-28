<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Extraction\Responses;

use Ark4ne\OpenApi\Configuration\SamplingConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\OperationContextFactory;
use Ark4ne\OpenApi\Extraction\Responses\OperationResponses;
use Ark4ne\OpenApi\Extraction\Responses\ResourceFormat;
use Ark4ne\OpenApi\Extraction\Responses\ResourceSchemas;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Tests\Fixtures\Integrations\TestResourceFormat;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\ArticleCollection;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\ArticleResource;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\AuthorResource;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\ResponseController;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Tests\Unit\Sampling\SamplingTestCase;
use Ark4ne\OpenApi\Types\TypeResolver;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * A resource a format supports is written by that format, wherever it is met.
 */
#[CoversClass(ResourceSchemas::class)]
#[CoversClass(OperationResponses::class)]
final class ResourceFormatsTest extends SamplingTestCase
{
    private const MEDIA_TYPE = 'application/x-test';

    private ?GenerationContext $context = null;

    private ?OperationResponses $responses = null;

    public function test_a_returned_resource_is_written_by_its_format(): void
    {
        $response = $this->only('show');

        self::assertSame([self::MEDIA_TYPE], array_keys($response->contents));
        Schemas::assertSame(['type' => 'object', 'properties' => ['document' => ['$ref' => '#/components/schemas/Article']], 'required' => ['document']], $response->contents[self::MEDIA_TYPE] ?? Schema::create());
        Schemas::assertSame(['type' => 'object', 'properties' => ['formatted' => ['type' => 'boolean']], 'required' => ['formatted']], $this->ctx()->components->get('Article') ?? Schema::create());
    }

    public function test_the_collections_of_a_resource_are_written_by_its_format(): void
    {
        foreach (['index', 'anonymous'] as $action) {
            $this->context = null;
            $response = $this->only($action);

            Schemas::assertSame(['type' => 'object', 'properties' => ['list' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Article']]], 'required' => ['list']], $response->contents[self::MEDIA_TYPE] ?? Schema::create(), $action);
        }
    }

    public function test_a_declared_paginated_resource_is_written_by_its_format(): void
    {
        $response = $this->only('paginated');

        Schemas::assertSame(['type' => 'object', 'properties' => ['paginator' => ['const' => 'Illuminate\Pagination\LengthAwarePaginator', 'type' => 'string']], 'required' => ['paginator']], $response->contents[self::MEDIA_TYPE] ?? Schema::create());
    }

    public function test_the_media_type_is_the_format_s_for_its_resources_and_their_collections(): void
    {
        $this->only('show');
        $resources = $this->responses?->resources();
        self::assertInstanceOf(ResourceSchemas::class, $resources);

        self::assertSame(self::MEDIA_TYPE, $resources->mediaType(ArticleResource::class));
        self::assertSame(self::MEDIA_TYPE, $resources->mediaType(ArticleCollection::class));
        self::assertSame('application/json', $resources->mediaType(AuthorResource::class));
        self::assertInstanceOf(ResourceFormat::class, $resources->format(ArticleCollection::class));
        self::assertNull($resources->format(AuthorResource::class));
    }

    public function test_a_format_that_fails_leaves_an_empty_object_with_a_warning(): void
    {
        $this->only('show', failing: true);

        Schemas::assertSame(['type' => 'object'], $this->ctx()->components->get('Article') ?? Schema::create());
        self::assertStringContainsString('cannot describe ' . ArticleResource::class . ': broken', implode("\n", array_map(strval(...), $this->ctx()->diagnostics->all())));
    }

    private function only(string $action, bool $failing = false): ResponseDescription
    {
        $this->router()->get("api/{$action}", [ResponseController::class, $action]);
        $context = $this->ctx();
        $definitions = $this->definitions($context);
        self::assertNotEmpty($definitions);
        $operation = (new OperationContextFactory(new TypeResolver()))->create($definitions[array_key_last($definitions)], $context);
        $types = new TypeResolver();
        $this->responses = OperationResponses::default(Samplers::default($this->samplingSession(), $types), $types, new TestResourceFormat(self::MEDIA_TYPE, [ArticleResource::class], $failing));
        $responses = $this->responses->describe($operation, $context);
        self::assertCount(1, $responses, implode("\n", array_map(strval(...), $context->diagnostics->all())));

        return $responses[0];
    }

    private function ctx(): GenerationContext
    {
        return $this->context ??= $this->context(sampling: new SamplingConfiguration(examples: false));
    }
}
