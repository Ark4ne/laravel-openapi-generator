<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApiV3;

use Ark4ne\ApiRevisions\Compilation\Shape;
use Ark4ne\ApiRevisions\Definition\Direction;
use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\SamplingConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Integrations\JsonApiV3\ValueSchemas;
use Ark4ne\OpenApi\Support\Patterns;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\ApiRevision;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\ArticleResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\ShowcaseResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\TagResource;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(ValueSchemas::class)]
#[CoversClass(Patterns::class)]
#[Group('json-api-v3')]
final class ValueSchemasTest extends JsonApiV3TestCase
{
    public function test_each_kind_of_value_gives_its_schema(): void
    {
        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 80, 'pattern' => '^[A-Z]', 'description' => 'The title.', 'deprecated' => true],
                'code' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                'rank' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                'ratio' => ['type' => ['number', 'null']],
                'active' => ['type' => 'boolean'],
                'day' => ['type' => 'string', 'format' => 'date'],
                'opened' => ['type' => 'string', 'format' => 'date-time'],
                'clock' => ['type' => ['string', 'null']],
                'stage' => ['type' => ['string', 'null'], 'enum' => ['draft', 'published', null]],
                'ranks' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 3, 'items' => ['type' => ['integer', 'null']]],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                'profile' => ['type' => 'object', 'properties' => ['city' => ['type' => ['string', 'null']], 'zip' => ['type' => 'integer', 'minimum' => 0]]],
                'price' => ['type' => 'string', 'description' => 'attributes.price'],
                'anything' => ['type' => 'string', 'description' => 'attributes.anything'],
                'tag' => ['type' => 'string', 'description' => 'attributes.tag'],
            ],
        ], $this->schemas()->members(Definitions::for(ShowcaseResource::class)->attributes, 'attributes.') ?? Schema::create());
    }

    public function test_a_custom_type_and_a_nested_resource_are_typed_by_the_sample_and_reported_once(): void
    {
        $schemas = $this->schemas();
        $attributes = Definitions::for(ShowcaseResource::class)->attributes;

        $schemas->members($attributes);
        $schemas->members($attributes);

        self::assertSame(implode("\n", [
            '[info] response: the custom type decimal is typed from a sample: its JSON Schema is not read',
            '[info] response: the nested resource ' . TagResource::class . ' is typed from a sample: its shape is not given by the definition',
        ]), $this->diagnostics());
    }

    public function test_the_first_example_is_written_when_the_examples_are_on(): void
    {
        $context = $this->context(sampling: new SamplingConfiguration(examples: true));
        $schemas = new ValueSchemas($context, static fn(string $path): Schema => Schema::create());
        $title = Definitions::for(ShowcaseResource::class)->attributes?->field('title');
        self::assertNotNull($title);

        self::assertSame('Hello', $schemas->field($title, 'title')->example);
    }

    public function test_the_fields_are_named_as_at_the_revision_of_their_shape(): void
    {
        $initial = $this->schemas()->members(Definitions::for(ArticleResource::class, ApiRevision::Initial)->attributes);
        $head = $this->schemas()->members(Definitions::for(ArticleResource::class)->attributes);

        self::assertSame(['name', 'status', 'notes', 'place'], array_keys($initial->properties ?? []));
        self::assertSame(['label', 'summary', 'notes', 'place'], array_keys($head->properties ?? []));
    }

    public function test_an_object_whose_schema_a_was_puts_in_force_has_the_members_of_that_schema(): void
    {
        $initial = $this->schemas()->members(Definitions::for(ArticleResource::class, ApiRevision::Initial)->attributes);
        $head = $this->schemas()->members(Definitions::for(ArticleResource::class)->attributes);

        Schemas::assertSame(['type' => 'object', 'properties' => ['town' => ['type' => ['string', 'null']]]], self::at($initial ?? Schema::create(), 'place'));
        Schemas::assertSame(['type' => 'object', 'properties' => ['city' => ['type' => 'string']]], self::at($head ?? Schema::create(), 'place'));
    }

    public function test_no_shape_gives_no_member(): void
    {
        self::assertNull($this->schemas()->members(null));
        self::assertNull($this->schemas()->members(Definitions::for(ShowcaseResource::class)->meta));
        self::assertNull($this->schemas()->members(new Shape(ApiRevision::Initial, Direction::Down, [])));
    }

    public function test_the_date_formats_of_the_configuration_replace_the_defaults(): void
    {
        $document = $this->ctx()->document;
        $context = GenerationContext::for(new Configuration([$document->name => $document], dateFormats: ['H:i:s' => 'time']), $document);
        $schemas = new ValueSchemas($context, static fn(string $path): Schema => Schema::create());
        $members = $schemas->members(Definitions::for(ShowcaseResource::class)->attributes);

        Schemas::assertSame(['type' => ['string', 'null'], 'format' => 'time'], self::at($members ?? Schema::create(), 'clock'));
        Schemas::assertSame(['type' => 'string'], self::at($members ?? Schema::create(), 'day'));
    }

    public function test_a_pattern_is_the_body_of_an_expression_without_flags(): void
    {
        self::assertSame('^[A-Z]', Patterns::ecma('/^[A-Z]/'));
        self::assertSame('a/b', Patterns::ecma('#a/b#'));
        self::assertNull(Patterns::ecma('/^[a-z]/i'));
        self::assertNull(Patterns::ecma('/'));
        self::assertNull(Patterns::ecma(''));
    }

    /**
     * A sample rendering every `Any` and `Custom` value as a string described by its path.
     */
    private function schemas(): ValueSchemas
    {
        return new ValueSchemas($this->ctx(), static fn(string $path): Schema => Schema::string()->description($path));
    }
}
