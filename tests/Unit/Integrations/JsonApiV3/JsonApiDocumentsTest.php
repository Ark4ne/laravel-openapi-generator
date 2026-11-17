<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApiV3;

use Ark4ne\OpenApi\Integrations\JsonApiV3\JsonApiResourceFormat;
use Ark4ne\OpenApi\Integrations\JsonApiV3\ResourceDefinitions;
use Ark4ne\OpenApi\Integrations\JsonApiV3\ValueSchemas;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types\CommentResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types\FailingHookResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types\LinksFailingResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types\PolymorphicResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types\PostResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types\RenderFailingResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types\TwinsResource;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * The relationships of the resource objects and the documents that carry them.
 */
#[CoversClass(JsonApiResourceFormat::class)]
#[CoversClass(ResourceDefinitions::class)]
#[CoversClass(ValueSchemas::class)]
#[Group('json-api-v3')]
final class JsonApiDocumentsTest extends JsonApiV3TestCase
{
    private const IDENTIFIER = ['type' => 'object', 'properties' => ['type' => ['type' => 'string', 'const' => 'posts'], 'id' => ['type' => 'string']], 'required' => ['type', 'id']];

    public function test_relationships_hold_resource_identifiers_with_their_links_and_meta(): void
    {
        $relationships = self::at($this->resourceObject(PolymorphicResource::class, 'Polymorphic'), 'relationships');

        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'data' => ['type' => 'array', 'items' => [...self::IDENTIFIER, 'properties' => [...self::IDENTIFIER['properties'], 'meta' => ['type' => 'object']]]],
                'links' => ['type' => 'object', 'properties' => ['self' => ['type' => 'string']]],
                'meta' => ['type' => 'object'],
            ],
        ], self::at($relationships, 'tags'));
    }

    public function test_a_polymorphic_relationship_has_the_types_of_its_resources_and_a_native_one_the_type_of_its_class(): void
    {
        $relationships = self::at($this->resourceObject(PolymorphicResource::class, 'Polymorphic'), 'relationships');

        Schemas::assertSame(['type' => 'string', 'enum' => ['posts', 'comments']], self::at($relationships, 'subject', 'data', 'type'));
        Schemas::assertSame(['type' => 'string', 'const' => 'native_authors'], self::at($relationships, 'author', 'data', 'type'));
        Schemas::assertSame(['type' => 'object'], self::at($relationships, 'author', 'links'));
    }

    public function test_values_typed_by_the_sample_are_inferred_from_the_resource_rendered_on_a_sample_of_its_model(): void
    {
        $post = $this->resourceObject(PostResource::class, 'Post');

        Schemas::assertSame(['type' => 'string'], self::at($post, 'attributes', 'title'));
        Schemas::assertSame(['type' => 'string', 'enum' => ['draft', 'published']], self::at($post, 'attributes', 'state'));
        Schemas::assertSame(['type' => 'integer'], self::at($post, 'attributes', 'computed'));
    }

    public function test_a_resource_failing_on_its_sample_is_described_by_its_definition_with_a_warning(): void
    {
        $failing = $this->resourceObject(RenderFailingResource::class, 'RenderFailing');

        Schemas::assertSame(['type' => 'string'], self::at($failing, 'attributes', 'title'));
        Schemas::assertSame([], self::at($failing, 'attributes', 'boom'));
        self::assertStringContainsString('cannot render ' . RenderFailingResource::class . ' on a sample: exploded', $this->diagnostics());
    }

    public function test_a_resource_the_package_cannot_compile_is_its_identification_with_a_warning(): void
    {
        $failing = $this->resourceObject(FailingHookResource::class, 'FailingHook');

        self::assertSame(['type', 'id'], array_keys($failing->properties ?? []));
        self::assertStringContainsString(FailingHookResource::class . ': cannot read the definition of ' . FailingHookResource::class . ': ', $this->diagnostics());
    }

    public function test_the_findings_of_the_compilation_are_relayed_once(): void
    {
        $this->resourceObject(LinksFailingResource::class, 'LinksFailing');
        $this->resources()->envelope(LinksFailingResource::class, Schema::ref('LinksFailing'), $this->ctx());

        self::assertSame(1, substr_count($this->diagnostics(), '(hook-failure)'));
    }

    public function test_a_document_has_jsonapi_its_data_the_included_resources_and_its_meta(): void
    {
        $document = $this->resources()->envelope(PostResource::class, Schema::ref('Post'), $this->ctx());

        self::assertSame(['jsonapi', 'data', 'included', 'meta'], array_keys($document->properties ?? []));
        self::assertSame(['jsonapi', 'data'], $document->required);
        Schemas::assertSame([
            'type' => 'array',
            'items' => [
                'oneOf' => [['$ref' => '#/components/schemas/Comment'], ['$ref' => '#/components/schemas/Post']],
                'discriminator' => ['propertyName' => 'type', 'mapping' => ['comments' => '#/components/schemas/Comment', 'posts' => '#/components/schemas/Post']],
            ],
        ], self::at($document, 'included'));
        Schemas::assertSame(['type' => 'object', 'properties' => ['generated' => ['type' => 'boolean']]], self::at($document, 'meta'));
    }

    public function test_included_resources_sharing_a_type_have_no_discriminator(): void
    {
        $document = $this->resources()->envelope(TwinsResource::class, Schema::ref('Twins'), $this->ctx());

        self::assertNull(self::items(self::at($document, 'included'))->discriminator);
        self::assertStringContainsString('share the type twin: written without discriminator', $this->diagnostics());
    }

    public function test_an_included_native_resource_is_a_resource_object_of_its_type_without_discriminator(): void
    {
        $items = self::items(self::at($this->resources()->envelope(PolymorphicResource::class, Schema::ref('Polymorphic'), $this->ctx()), 'included'));

        self::assertNull($items->discriminator);
        $native = $items->oneOf[2] ?? null;
        self::assertInstanceOf(Schema::class, $native);
        Schemas::assertSame(['type' => 'string', 'const' => 'native_authors'], self::at($native, 'type'));
    }

    public function test_a_resource_without_relationship_includes_nothing(): void
    {
        self::assertSame(['jsonapi', 'data'], array_keys($this->resources()->envelope(RenderFailingResource::class, Schema::ref('RenderFailing'), $this->ctx())->properties ?? []));
    }

    public function test_the_document_meta_joins_the_pagination_s_which_stays_required(): void
    {
        $paginated = $this->resources()->collection(PostResource::class, LengthAwarePaginator::class, $this->ctx());

        self::assertSame(['jsonapi', 'data', 'links', 'meta'], $paginated->required);
        self::assertContains('generated', array_keys(self::at($paginated, 'meta')->properties ?? []));
        self::assertContains('total', self::at($paginated, 'meta')->required ?? []);
    }

    public function test_a_collection_lists_its_resources(): void
    {
        $list = $this->resources()->collection(CommentResource::class, null, $this->ctx());

        Schemas::assertSame(['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Comment']], self::at($list, 'data'));
    }

    public function test_a_request_sends_the_resource_object_under_data(): void
    {
        Schemas::assertSame([
            'type' => 'object',
            'properties' => ['data' => ['$ref' => '#/components/schemas/Comment']],
            'required' => ['data'],
        ], $this->resources()->request(CommentResource::class, $this->ctx()));
    }

    private static function items(Schema $array): Schema
    {
        self::assertInstanceOf(Schema::class, $array->items);

        return $array->items;
    }

    /**
     * @param class-string<\Illuminate\Http\Resources\Json\JsonResource> $resource
     */
    private function resourceObject(string $resource, string $name): Schema
    {
        $this->resources()->schema($resource, $this->ctx());

        return $this->ctx()->components->get($name) ?? Schema::create();
    }
}
