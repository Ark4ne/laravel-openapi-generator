<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApiV3;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Integrations\JsonApiV3\JsonApiObjects;
use Ark4ne\OpenApi\Integrations\JsonApiV3\JsonApiResourceFormat;
use Ark4ne\OpenApi\Integrations\JsonApiV3\ResourceDefinitions;
use Ark4ne\OpenApi\Integrations\JsonApiV3\Revisions;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\ApiRevision;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\ArticleResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\MemberResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\TagResource;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(JsonApiResourceFormat::class)]
#[CoversClass(ResourceDefinitions::class)]
#[CoversClass(JsonApiObjects::class)]
#[Group('json-api-v3')]
final class JsonApiResourceFormatTest extends JsonApiV3TestCase
{
    public function test_it_supports_the_json_api_resources_only(): void
    {
        $format = new JsonApiResourceFormat();

        self::assertTrue($format->supports(ArticleResource::class));
        self::assertFalse($format->supports(TagResource::class));
        self::assertSame('application/vnd.api+json', $format->mediaType());
    }

    public function test_a_resource_object_has_the_members_of_its_definition_at_the_head(): void
    {
        $article = $this->article($this->documented([]));

        self::assertSame(['type', 'id', 'attributes', 'relationships', 'links', 'meta'], array_keys($article->properties ?? []));
        self::assertSame(['type', 'id'], $article->required);
        Schemas::assertSame(['type' => 'string', 'const' => 'articles'], self::at($article, 'type'));
        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'label' => ['type' => 'string', 'maxLength' => 120, 'description' => 'The title.'],
                'summary' => ['type' => ['string', 'null']],
                'notes' => ['type' => 'string', 'description' => 'Only when the sparse fieldset names it (`fields[articles]`).'],
                'place' => ['type' => 'object', 'properties' => ['city' => ['type' => 'string']]],
            ],
        ], self::at($article, 'attributes'));
        Schemas::assertSame(['type' => 'object', 'properties' => ['created_at' => ['type' => ['string', 'null'], 'format' => 'date-time']]], self::at($article, 'meta'));
        self::assertSame(['author'], array_keys(self::at($article, 'relationships')->properties ?? []));
    }

    public function test_the_values_and_relationships_are_those_of_the_revision_of_the_document(): void
    {
        $article = $this->article($this->documented(['revision' => ApiRevision::Initial]));

        self::assertSame(['name', 'status', 'notes', 'place'], array_keys(self::at($article, 'attributes')->properties ?? []));
        Schemas::assertSame(['type' => 'string', 'enum' => ['draft', 'published']], self::at($article, 'attributes', 'status'));
        self::assertSame(['writer'], array_keys(self::at($article, 'relationships')->properties ?? []));
        Schemas::assertSame(['type' => 'string', 'const' => 'writers'], self::at($article, 'relationships', 'writer', 'data', 'type'));
    }

    public function test_the_links_of_a_resource_object_are_urls_or_link_objects_by_name(): void
    {
        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'self' => [
                    'oneOf' => [
                        ['type' => 'string', 'format' => 'uri-reference'],
                        ['type' => 'object', 'properties' => ['href' => ['type' => 'string', 'format' => 'uri-reference'], 'meta' => ['type' => 'object']], 'required' => ['href']],
                        ['type' => 'null'],
                    ],
                ],
            ],
        ], self::at($this->article($this->documented([])), 'links'));
    }

    public function test_the_document_meta_is_that_of_the_definition(): void
    {
        $context = $this->documented([]);
        $resources = $this->resources(new ResourceDefinitions(new Revisions()));
        $data = $resources->schema(ArticleResource::class, $context);
        $document = (new JsonApiResourceFormat(new ResourceDefinitions(new Revisions())))->document(ArticleResource::class, $data, $resources, $context);

        Schemas::assertSame(['type' => 'object', 'properties' => ['catalog' => ['type' => 'string']]], self::at($document, 'meta'));
    }

    public function test_a_resource_absent_from_the_revision_is_reported(): void
    {
        $context = $this->documented(['revision' => ApiRevision::Initial]);
        $definitions = new ResourceDefinitions(new Revisions());

        self::assertNull($definitions->of(MemberResource::class, $context, Stage::Response, MemberResource::class));
        self::assertNotNull($definitions->of(MemberResource::class, $context, Stage::Response, MemberResource::class, null));
        self::assertSame('[warning] response: ' . MemberResource::class . ' does not exist at the revision 2026-09-01: exclude its routes from the document (routes.exclude)', implode("\n", array_map(strval(...), $context->diagnostics->all())));
    }

    public function test_a_definition_is_read_once_per_context_and_revision(): void
    {
        $context = $this->documented(['revision' => ApiRevision::Initial]);
        $definitions = new ResourceDefinitions(new Revisions());

        self::assertSame($definitions->of(ArticleResource::class, $context, Stage::Response, 'a'), $definitions->of(ArticleResource::class, $context, Stage::Response, 'b'));
        self::assertNotSame($definitions->of(ArticleResource::class, $context, Stage::Response, 'a'), $definitions->of(ArticleResource::class, $context, Stage::Response, 'a', null));
    }

    private function article(GenerationContext $context): Schema
    {
        $this->resources(new ResourceDefinitions(new Revisions()))->schema(ArticleResource::class, $context);

        return $context->components->get('Article') ?? Schema::create();
    }
}
