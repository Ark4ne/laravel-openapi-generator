<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApiV2;

use Ark4ne\OpenApi\Configuration\SamplingConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Extraction\Responses\ResourceSchemas;
use Ark4ne\OpenApi\Integrations\JsonApiV2\AttributeSchemas;
use Ark4ne\OpenApi\Integrations\JsonApiV2\JsonApiObjects;
use Ark4ne\OpenApi\Integrations\JsonApiV2\JsonApiResourceFormat;
use Ark4ne\OpenApi\Integrations\JsonApiV2\ResourceDefinitions;
use Ark4ne\OpenApi\Sampling\ModelSchemas;
use Ark4ne\OpenApi\Sampling\RelationLoader;
use Ark4ne\OpenApi\Sampling\ResourceModels;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Sampling\SchemaInferrer;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Resources\CommentResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Resources\PostResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Resources\UserResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types\FailingHookResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types\RenamedHookResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types\RenderFailingResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types\ShowcaseResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types\TagCollection;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types\TaggedResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types\TwinAResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types\TwinBResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types\TwinsResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types\UnsampledResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types\UntypedNativeHolderResource;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources\AuthorResource;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApi\JsonApiTestCase;
use Ark4ne\OpenApi\Types\TypeResolver;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(JsonApiResourceFormat::class)]
#[CoversClass(ResourceDefinitions::class)]
#[CoversClass(AttributeSchemas::class)]
#[CoversClass(JsonApiObjects::class)]
#[Group('json-api-v2')]
final class JsonApiResourceFormatTest extends JsonApiTestCase
{
    private const IDENTIFIER_POST = ['type' => 'object', 'properties' => ['type' => ['type' => 'string', 'const' => 'post'], 'id' => ['type' => 'string']], 'required' => ['type', 'id']];

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

        self::assertSame(['type', 'id', 'attributes', 'relationships', 'meta'], array_keys($user->properties ?? []));
        self::assertSame(['type', 'id'], $user->required);
        Schemas::assertSame(['type' => 'string'], self::at($user, 'id'));
        Schemas::assertSame(['type' => 'string', 'const' => 'user'], self::at($user, 'type'));
    }

    public function test_the_definition_gives_the_types_of_the_attributes(): void
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

    public function test_a_date_takes_the_format_of_its_definition(): void
    {
        $meta = self::at($this->resourceObject(UserResource::class, 'User'), 'meta');

        Schemas::assertSame(['type' => ['string', 'null'], 'format' => 'date-time'], self::at($meta, 'created_at'));
        Schemas::assertSame(['type' => ['string', 'null'], 'format' => 'date'], self::at($meta, 'updated_at'));
    }

    public function test_mixed_values_are_inferred_from_the_resource_rendered_on_a_sample_and_an_enum_from_the_cast_of_the_model(): void
    {
        $post = $this->resourceObject(PostResource::class, 'Post');

        Schemas::assertSame(['type' => 'string'], self::at($post, 'attributes', 'title'));
        Schemas::assertSame(['type' => ['string', 'null'], 'enum' => ['draft', 'published', null]], self::at($post, 'attributes', 'status'));
        Schemas::assertSame(['type' => 'object', 'properties' => ['self' => ['type' => 'string']]], self::at($post, 'links'));
    }

    public function test_a_mixed_value_without_sample_has_an_empty_schema(): void
    {
        $unsampled = $this->resourceObject(UnsampledResource::class, 'Unsampled');

        Schemas::assertSame([], self::at($unsampled, 'attributes', 'raw'));
        Schemas::assertSame(['type' => ['string', 'null']], self::at($unsampled, 'attributes', 'label'));
    }

    public function test_a_resource_failing_on_its_sample_is_described_by_its_definition_with_a_warning(): void
    {
        $failing = $this->resourceObject(RenderFailingResource::class, 'RenderFailing');

        Schemas::assertSame(['type' => ['string', 'null']], self::at($failing, 'attributes', 'title'));
        Schemas::assertSame([], self::at($failing, 'attributes', 'boom'));
        self::assertStringContainsString('cannot render ' . RenderFailingResource::class . ' on a sample: exploded', $this->diagnostics());
    }

    public function test_relationships_hold_resource_identifiers_with_their_links_and_meta(): void
    {
        $user = $this->resourceObject(UserResource::class, 'User');
        $post = $this->resourceObject(PostResource::class, 'Post');

        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'data' => ['type' => 'array', 'items' => self::IDENTIFIER_POST],
                'links' => ['type' => 'object', 'properties' => ['self' => ['type' => 'string'], 'related' => ['type' => 'string']]],
            ],
        ], self::at($user, 'relationships', 'posts'));
        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'data' => ['type' => ['object', 'null'], 'properties' => ['type' => ['type' => 'string', 'const' => 'user'], 'id' => ['type' => 'string']], 'required' => ['type', 'id']],
                'meta' => ['type' => 'object'],
            ],
        ], self::at($post, 'relationships', 'user'));
        self::assertSame(['user', 'comments'], array_keys(self::at($post, 'relationships')->properties ?? []));
    }

    public function test_a_relationship_to_a_collection_lists_the_resource_it_collects_and_links_declared_by_a_closure_are_free(): void
    {
        $tagged = $this->resourceObject(TaggedResource::class, 'Tagged');

        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'data' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['type' => ['type' => 'string', 'const' => 'tag'], 'id' => ['type' => 'string']], 'required' => ['type', 'id']]],
                'links' => ['type' => 'object'],
            ],
        ], self::at($tagged, 'relationships', 'tags'));
    }

    public function test_the_identifiers_have_meta_when_the_relationship_declares_identifier_meta(): void
    {
        $labels = self::at($this->resourceObject(TaggedResource::class, 'Tagged'), 'relationships', 'labels');

        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'data' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['type' => ['type' => 'string', 'const' => 'tag'], 'id' => ['type' => 'string'], 'meta' => ['type' => 'object']], 'required' => ['type', 'id']]],
                'meta' => ['type' => 'object'],
            ],
        ], $labels);
    }

    public function test_a_polymorphic_relationship_has_the_types_of_its_resources_and_a_native_one_the_type_of_its_class(): void
    {
        $showcase = $this->resourceObject(ShowcaseResource::class, 'Showcase');

        Schemas::assertSame(['type' => 'string', 'enum' => ['post', 'comment']], self::at($showcase, 'relationships', 'subject', 'data', 'type'));
        Schemas::assertSame(['type' => 'string', 'const' => 'native_authors'], self::at($showcase, 'relationships', 'author', 'data', 'type'));
        Schemas::assertSame(['type' => 'string'], self::at($this->resourceObject(UntypedNativeHolderResource::class, 'UntypedNativeHolder'), 'relationships', 'owner', 'data', 'type'));
    }

    public function test_a_collection_class_is_written_as_the_collection_of_its_resource(): void
    {
        $paginated = $this->resources()->collection(TagCollection::class, LengthAwarePaginator::class, $this->ctx());

        Schemas::assertSame(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Tag']], self::at($paginated, 'data'));
        self::assertFalse($this->ctx()->components->has('TagCollection'));
    }

    public function test_the_type_defaults_to_the_class_name(): void
    {
        Schemas::assertSame(['type' => 'string', 'const' => 'comment'], self::at($this->resourceObject(CommentResource::class, 'Comment'), 'type'));
    }

    public function test_a_resource_the_package_cannot_compile_is_its_identification_with_a_warning(): void
    {
        $renamed = $this->resourceObject(RenamedHookResource::class, 'RenamedHook');
        $document = $this->resources()->envelope(RenamedHookResource::class, Schema::ref('RenamedHook'), $this->ctx());

        Schemas::assertSame(['type' => 'object', 'properties' => ['type' => ['type' => 'string'], 'id' => ['type' => 'string']], 'required' => ['type', 'id']], $renamed);
        self::assertSame(['jsonapi', 'data'], array_keys($document->properties ?? []));
        self::assertStringContainsString(RenamedHookResource::class . ': cannot read the definition of ' . RenamedHookResource::class . ': ', $this->diagnostics());
        self::assertSame(1, substr_count($this->diagnostics(), 'cannot read the definition'));
    }

    public function test_the_findings_of_the_compilation_are_relayed_once(): void
    {
        $failing = $this->resourceObject(FailingHookResource::class, 'FailingHook');
        $this->resources()->envelope(FailingHookResource::class, Schema::ref('FailingHook'), $this->ctx());

        self::assertSame(['type', 'id'], array_keys($failing->properties ?? []));
        self::assertStringContainsString(FailingHookResource::class . ': toAttributes() threw on the probe', $this->diagnostics());
        self::assertSame(1, substr_count($this->diagnostics(), '(hook-failure)'));
    }

    public function test_a_document_has_jsonapi_its_data_the_included_resources_and_its_meta(): void
    {
        $resources = $this->resources();
        $document = $resources->envelope(PostResource::class, $resources->schema(PostResource::class, $this->ctx()), $this->ctx());

        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'jsonapi' => ['$ref' => '#/components/schemas/JsonApi'],
                'data' => ['$ref' => '#/components/schemas/Post'],
                'included' => ['type' => 'array', 'items' => [
                    'oneOf' => [['$ref' => '#/components/schemas/User'], ['$ref' => '#/components/schemas/Comment'], ['$ref' => '#/components/schemas/Post']],
                    'discriminator' => ['propertyName' => 'type', 'mapping' => ['user' => '#/components/schemas/User', 'comment' => '#/components/schemas/Comment', 'post' => '#/components/schemas/Post']],
                ]],
                'meta' => ['type' => 'object', 'properties' => ['version' => ['type' => 'string']]],
            ],
            'required' => ['jsonapi', 'data'],
        ], $document);
        Schemas::assertSame(['type' => 'object', 'properties' => ['version' => ['type' => 'string', 'const' => '1.1']], 'required' => ['version']], $this->ctx()->components->get('JsonApi') ?? Schema::create());
    }

    public function test_included_resources_sharing_a_type_have_no_discriminator(): void
    {
        $resources = $this->resources();
        $document = $resources->envelope(TwinsResource::class, $resources->schema(TwinsResource::class, $this->ctx()), $this->ctx());

        Schemas::assertSame(['type' => 'array', 'items' => ['oneOf' => [['$ref' => '#/components/schemas/TwinA'], ['$ref' => '#/components/schemas/TwinB']]]], self::at($document, 'included'));
        self::assertStringContainsString('the included resources ' . TwinAResource::class . ' and ' . TwinBResource::class . ' share the type twin: written without discriminator', $this->diagnostics());
    }

    public function test_an_included_native_resource_is_a_resource_object_of_its_type_without_discriminator(): void
    {
        $resources = $this->resources();
        $included = self::at($resources->envelope(ShowcaseResource::class, $resources->schema(ShowcaseResource::class, $this->ctx()), $this->ctx()), 'included');
        $items = $included->items;
        self::assertInstanceOf(Schema::class, $items);
        $members = $items->oneOf ?? [];

        self::assertNull($items->discriminator);
        self::assertCount(4, $members);
        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'const' => 'native_authors'],
                'id' => ['type' => 'string'],
                'attributes' => ['type' => 'object'],
                'relationships' => ['type' => 'object'],
                'links' => ['type' => 'object'],
                'meta' => ['type' => 'object'],
            ],
            'required' => ['type', 'id'],
        ], $members[3] instanceof Schema ? $members[3] : Schema::create());
    }

    public function test_a_resource_without_relationship_includes_nothing(): void
    {
        $resources = $this->resources();

        self::assertSame(['jsonapi', 'data'], array_keys($resources->envelope(UnsampledResource::class, $resources->schema(UnsampledResource::class, $this->ctx()), $this->ctx())->properties ?? []));
    }

    public function test_an_only_included_resource_is_not_a_one_of(): void
    {
        $resources = $this->resources();

        Schemas::assertSame(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Tag']], self::at($resources->envelope(TaggedResource::class, $resources->schema(TaggedResource::class, $this->ctx()), $this->ctx()), 'included'));
    }

    public function test_the_document_meta_joins_the_pagination_s_which_stays_required(): void
    {
        $meta = self::at($this->resources()->collection(PostResource::class, LengthAwarePaginator::class, $this->ctx()), 'meta');

        self::assertSame(['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total'], $meta->required);
        self::assertSame(['current_page', 'from', 'last_page', 'links', 'path', 'per_page', 'to', 'total', 'version'], array_keys($meta->properties ?? []));
    }

    public function test_a_request_sends_the_resource_object_under_data(): void
    {
        Schemas::assertSame(['type' => 'object', 'properties' => ['data' => ['$ref' => '#/components/schemas/Post']], 'required' => ['data']], $this->resources()->request(PostResource::class, $this->ctx()));
    }

    public function test_a_collection_lists_its_resources_and_a_paginated_one_has_laravel_s_links_and_meta(): void
    {
        $resources = $this->resources();
        $list = $resources->collection(CommentResource::class, null, $this->ctx());
        $paginated = $resources->collection(CommentResource::class, LengthAwarePaginator::class, $this->ctx());

        self::assertSame(['jsonapi', 'data', 'included'], array_keys($list->properties ?? []));
        Schemas::assertSame(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Comment']], self::at($list, 'data'));
        self::assertSame(['jsonapi', 'data', 'links', 'meta', 'included'], array_keys($paginated->properties ?? []));
        self::assertSame(['jsonapi', 'data', 'links', 'meta'], $paginated->required);
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

    private function diagnostics(): string
    {
        return implode("\n", array_map(strval(...), $this->ctx()->diagnostics->all()));
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
