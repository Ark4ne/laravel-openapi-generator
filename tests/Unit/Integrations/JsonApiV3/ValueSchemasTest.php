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
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\ApiRevision;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\ArticleResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\ShowcaseResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types\BrokenNestedResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types\NestingResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types\NodeResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types\PatternedResource;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApiWriter\Enums\Version;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(ValueSchemas::class)]
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
                'clock' => ['type' => ['string', 'null'], 'pattern' => '^\\d{2}:\\d{2}:\\d{2}$', '$comment' => 'PHP date format H:i:s'],
                'stage' => ['type' => ['string', 'null'], 'enum' => ['draft', 'published', null]],
                'ranks' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 3, 'items' => ['type' => ['integer', 'null']]],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                'profile' => ['type' => 'object', 'properties' => ['city' => ['type' => ['string', 'null']], 'zip' => ['type' => 'integer', 'minimum' => 0]]],
                'price' => ['type' => 'string', 'description' => 'attributes.price'],
                'anything' => ['type' => 'string', 'description' => 'attributes.anything'],
                'tag' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
            ],
        ], $this->schemas()->members(Definitions::for(ShowcaseResource::class)->attributes, 'attributes.') ?? Schema::create());
    }

    public function test_a_custom_type_is_typed_by_the_sample_and_reported_once(): void
    {
        $schemas = $this->schemas();
        $attributes = Definitions::for(ShowcaseResource::class)->attributes;

        $schemas->members($attributes);
        $schemas->members($attributes);

        self::assertSame('[info] response: the custom type decimal is typed from a sample: its JSON Schema is not read', $this->diagnostics());
    }

    public function test_a_nested_resource_has_the_members_of_its_shape_and_stops_where_it_nests_itself(): void
    {
        $node = self::at($this->schemas()->members(Definitions::for(NestingResource::class)->attributes, 'attributes.') ?? Schema::create(), 'node');

        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'label' => ['type' => 'string'],
                'parent' => ['type' => 'string', 'description' => 'attributes.node.parent'],
            ],
        ], $node);
        self::assertStringContainsString('[info] response: the nested resource ' . NodeResource::class . ' nests itself: typed from a sample from there', $this->diagnostics());
    }

    public function test_a_nested_resource_whose_shape_cannot_be_compiled_is_typed_by_the_sample_with_a_warning(): void
    {
        $broken = self::at($this->schemas()->members(Definitions::for(NestingResource::class)->attributes, 'attributes.') ?? Schema::create(), 'broken');

        Schemas::assertSame(['type' => 'string', 'description' => 'attributes.broken'], $broken);
        self::assertMatchesRegularExpression('/^\\[warning\\] response: cannot read the shape of the nested resource ' . preg_quote(BrokenNestedResource::class, '/') . ': .*\\(no shape\\).*[^.]; typed from a sample$/m', $this->diagnostics());
    }

    public function test_the_patterns_and_the_date_formats_are_written_as_the_base_exports_them(): void
    {
        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'slug' => ['type' => 'string', 'pattern' => '^[a-z]+$'],
                'word' => ['type' => 'string', '$comment' => 'PCRE /^abc$/i'],
                'day' => ['type' => 'string', 'format' => 'date'],
                'iso_day' => ['type' => 'string', 'format' => 'date'],
                'local_day' => ['type' => 'string', 'pattern' => '^\\d{2}/\\d{2}/\\d{4}$', '$comment' => 'PHP date format d/m/Y'],
                'spoken_day' => ['type' => 'string', '$comment' => 'PHP date format D, d M Y'],
                'at' => ['type' => 'string', 'format' => 'date-time'],
                'at_extended' => ['type' => 'string', 'format' => 'date-time'],
                'at_sql' => ['type' => 'string', 'pattern' => '^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$', '$comment' => 'PHP date format Y-m-d H:i:s'],
                'described_day' => ['type' => 'string', 'pattern' => '^\\d{2}/\\d{2}/\\d{4}$', 'description' => 'The day.', '$comment' => 'PHP date format d/m/Y'],
            ],
        ], $this->schemas()->members(Definitions::for(PatternedResource::class)->attributes) ?? Schema::create());
    }

    public function test_before_3_1_the_comment_is_said_in_the_description_after_that_of_the_type(): void
    {
        $context = $this->context(version: Version::V3_0_4, sampling: new SamplingConfiguration(examples: false));
        $members = (new ValueSchemas($context, static fn(string $path): Schema => Schema::create(), Definitions::projection()->shapes))
            ->members(Definitions::for(PatternedResource::class)->attributes) ?? Schema::create();

        Schemas::assertSame(['type' => 'string', 'description' => 'PCRE /^abc$/i'], self::at($members, 'word'));
        Schemas::assertSame(['type' => 'string', 'pattern' => '^\\d{2}/\\d{2}/\\d{4}$', 'description' => "The day.\n\nPHP date format d/m/Y"], self::at($members, 'described_day'));
    }

    public function test_the_first_example_is_written_when_the_examples_are_on(): void
    {
        $context = $this->context(sampling: new SamplingConfiguration(examples: true));
        $schemas = new ValueSchemas($context, static fn(string $path): Schema => Schema::create(), Definitions::projection()->shapes);
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

    public function test_the_date_formats_of_the_configuration_leave_a_declared_date_as_the_base_exports_it(): void
    {
        $document = $this->ctx()->document;
        $context = GenerationContext::for(new Configuration([$document->name => $document], dateFormats: ['H:i:s' => 'time']), $document);
        $schemas = new ValueSchemas($context, static fn(string $path): Schema => Schema::create(), Definitions::projection()->shapes);
        $members = $schemas->members(Definitions::for(ShowcaseResource::class)->attributes);

        Schemas::assertSame(['type' => ['string', 'null'], 'pattern' => '^\\d{2}:\\d{2}:\\d{2}$', '$comment' => 'PHP date format H:i:s'], self::at($members ?? Schema::create(), 'clock'));
        Schemas::assertSame(['type' => 'string', 'format' => 'date'], self::at($members ?? Schema::create(), 'day'));
    }

    /**
     * A sample rendering every `Any` and `Custom` value as a string described by its path.
     */
    private function schemas(): ValueSchemas
    {
        return new ValueSchemas($this->ctx(), static fn(string $path): Schema => Schema::string()->description($path), Definitions::projection()->shapes);
    }
}
