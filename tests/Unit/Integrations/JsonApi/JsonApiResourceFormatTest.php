<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApi;

use Ark4ne\OpenApi\Configuration\SamplingConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Extraction\Responses\ResourceSchemas;
use Ark4ne\OpenApi\Integrations\JsonApi\JsonApiResourceFormat;
use Ark4ne\OpenApi\Sampling\ModelSchemas;
use Ark4ne\OpenApi\Sampling\RelationLoader;
use Ark4ne\OpenApi\Sampling\ResourceModels;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Sampling\SchemaInferrer;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Resources\CommentResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Resources\PostResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Resources\UserResource;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources\AuthorResource;
use Ark4ne\OpenApi\Tests\Fixtures\Types\BrokenJsonApiResource;
use Ark4ne\OpenApi\Tests\Fixtures\Types\UnsampledJsonApiResource;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Types\TypeResolver;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(JsonApiResourceFormat::class)]
#[Group('json-api')]
final class JsonApiResourceFormatTest extends JsonApiTestCase
{
    private const IDENTIFIER_POST = ['type' => 'object', 'properties' => ['type' => ['type' => 'string', 'const' => 'post'], 'id' => ['type' => 'integer']], 'required' => ['type', 'id']];

    private ?GenerationContext $context = null;

    private ?ResourceSchemas $resources = null;

    public function test_it_supports_the_json_api_resources_only(): void
    {
        $format = new JsonApiResourceFormat();

        self::assertTrue($format->supports(UserResource::class));
        self::assertFalse($format->supports(AuthorResource::class));
        self::assertSame('application/vnd.api+json', $format->mediaType());
    }

    public function test_a_resource_object_has_its_identification_and_its_members(): void
    {
        $user = $this->resourceObject(UserResource::class, 'User');

        self::assertSame(['id', 'type', 'attributes', 'relationships', 'meta'], array_keys($user->properties ?? []));
        self::assertSame(['id', 'type'], $user->required);
        Schemas::assertSame(['type' => 'integer'], self::at($user, 'id'));
        Schemas::assertSame(['type' => 'string', 'const' => 'user'], self::at($user, 'type'));
    }

    public function test_the_descriptors_give_the_types_of_the_attributes(): void
    {
        $attributes = self::at($this->resourceObject(UserResource::class, 'User'), 'attributes');

        Schemas::assertSame(['type' => ['string', 'null']], self::at($attributes, 'name'));
        Schemas::assertSame(['type' => ['string', 'null']], self::at($attributes, 'email'));
        Schemas::assertSame([
            'type' => ['object', 'null'],
            'properties' => [
                'name' => ['type' => ['string', 'null']],
                'initials' => ['type' => ['string', 'null']],
                'verified' => ['type' => 'boolean'],
                'hidden' => [],
                'scores' => ['type' => ['object', 'null'], 'properties' => ['rank' => ['type' => ['integer', 'null']], 'ratio' => ['type' => ['number', 'null']]]],
            ],
        ], self::at($attributes, 'profile'));
    }

    public function test_a_date_takes_the_format_of_its_descriptor(): void
    {
        $meta = self::at($this->resourceObject(UserResource::class, 'User'), 'meta');

        Schemas::assertSame(['type' => ['string', 'null'], 'format' => 'date-time'], self::at($meta, 'created_at'));
        Schemas::assertSame(['type' => ['string', 'null'], 'format' => 'date'], self::at($meta, 'updated_at'));
    }

    public function test_raw_values_are_inferred_from_a_sample_and_an_enum_from_the_cast_of_the_model(): void
    {
        $post = $this->resourceObject(PostResource::class, 'Post');

        Schemas::assertSame(['type' => 'string'], self::at($post, 'attributes', 'title'));
        Schemas::assertSame(['type' => ['string', 'null'], 'enum' => ['draft', 'published', null]], self::at($post, 'attributes', 'status'));
        Schemas::assertSame(['type' => 'object', 'properties' => ['self' => ['type' => 'string']]], self::at($post, 'links'));
    }

    public function test_relationships_hold_resource_identifiers_with_their_links_and_meta(): void
    {
        $user = $this->resourceObject(UserResource::class, 'User');
        $post = $this->resourceObject(PostResource::class, 'Post');

        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'data' => ['type' => 'array', 'items' => self::IDENTIFIER_POST],
                'links' => ['type' => 'object', 'properties' => ['self' => ['type' => 'string'], 'related' => ['type' => 'string']], 'required' => ['self', 'related']],
            ],
        ], self::at($user, 'relationships', 'posts'));
        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'data' => ['type' => ['object', 'null'], 'properties' => ['type' => ['type' => 'string', 'const' => 'user'], 'id' => ['type' => 'integer']], 'required' => ['type', 'id']],
                'meta' => ['type' => 'object', 'properties' => ['role' => ['type' => 'string']], 'required' => ['role']],
            ],
        ], self::at($post, 'relationships', 'user'));
        self::assertSame(['user', 'comments'], array_keys(self::at($post, 'relationships')->properties ?? []));
    }

    public function test_the_type_defaults_to_the_class_name(): void
    {
        Schemas::assertSame(['type' => 'string', 'const' => 'comment'], self::at($this->resourceObject(CommentResource::class, 'Comment'), 'type'));
    }

    public function test_a_resource_without_sample_has_a_string_identifier(): void
    {
        Schemas::assertSame(['type' => 'string'], self::at($this->resourceObject(UnsampledJsonApiResource::class, 'UnsampledJsonApi'), 'id'));
    }

    public function test_a_failing_member_is_left_out_with_a_warning(): void
    {
        $broken = $this->resourceObject(BrokenJsonApiResource::class, 'BrokenJsonApi');

        self::assertSame(['id', 'type'], array_keys($broken->properties ?? []));
        self::assertStringContainsString('cannot run ' . BrokenJsonApiResource::class . '::toAttributes(): broken', implode("\n", array_map(strval(...), $this->ctx()->diagnostics->all())));
    }

    public function test_a_document_has_its_data_the_included_resources_and_its_meta(): void
    {
        $resources = $this->resources();
        $document = $resources->envelope(PostResource::class, $resources->schema(PostResource::class, $this->ctx()), $this->ctx());

        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'data' => ['$ref' => '#/components/schemas/Post'],
                'included' => ['type' => 'array', 'items' => [
                    'oneOf' => [['$ref' => '#/components/schemas/User'], ['$ref' => '#/components/schemas/Comment'], ['$ref' => '#/components/schemas/Post']],
                    'discriminator' => ['propertyName' => 'type', 'mapping' => ['user' => '#/components/schemas/User', 'comment' => '#/components/schemas/Comment', 'post' => '#/components/schemas/Post']],
                ]],
                'meta' => ['type' => 'object', 'properties' => ['version' => ['type' => 'string']]],
            ],
            'required' => ['data'],
        ], $document);
    }

    public function test_a_resource_without_relationship_includes_nothing(): void
    {
        $resources = $this->resources();

        Schemas::assertSame(['type' => 'object', 'properties' => ['data' => ['$ref' => '#/components/schemas/UnsampledJsonApi']], 'required' => ['data']], $resources->envelope(UnsampledJsonApiResource::class, $resources->schema(UnsampledJsonApiResource::class, $this->ctx()), $this->ctx()));
    }

    public function test_a_collection_lists_its_resources_and_a_paginated_one_has_laravel_s_links_and_meta(): void
    {
        $resources = $this->resources();
        $list = $resources->collection(CommentResource::class, null, $this->ctx());
        $paginated = $resources->collection(CommentResource::class, LengthAwarePaginator::class, $this->ctx());

        self::assertSame(['data', 'included'], array_keys($list->properties ?? []));
        Schemas::assertSame(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Comment']], self::at($list, 'data'));
        self::assertSame(['data', 'links', 'meta', 'included'], array_keys($paginated->properties ?? []));
        self::assertSame(['data', 'links', 'meta'], $paginated->required);
        self::assertSame('application/vnd.api+json', $resources->mediaType(CommentResource::class));
    }

    /**
     * @param class-string<\Illuminate\Http\Resources\Json\JsonResource> $resource
     */
    private function resourceObject(string $resource, string $name): Schema
    {
        $this->resources()->schema($resource, $this->ctx());

        return $this->ctx()->components->get($name) ?? Schema::create();
    }

    private function resources(): ResourceSchemas
    {
        $types = new TypeResolver();
        $samplers = Samplers::default($this->samplingSession(), $types);

        return $this->resources ??= new ResourceSchemas(new ResourceModels($types), $samplers, new SchemaInferrer(new ModelSchemas($types)), new RelationLoader($samplers), [new JsonApiResourceFormat()]);
    }

    private function ctx(): GenerationContext
    {
        return $this->context ??= $this->context(sampling: new SamplingConfiguration(examples: false));
    }

    private static function at(Schema $schema, string ...$path): Schema
    {
        foreach ($path as $segment) {
            $next = $schema->properties[$segment] ?? null;
            self::assertInstanceOf(Schema::class, $next, $segment);
            $schema = $next;
        }

        return $schema;
    }
}
