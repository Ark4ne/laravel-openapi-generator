<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApiV2;

use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Integrations\JsonApiV2\AttributeSchemas;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Status;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types\Suit;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(AttributeSchemas::class)]
#[Group('json-api-v2')]
final class AttributeSchemasTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(): AttributeDefinition, array<string, mixed>}>
     */
    public static function valueTypes(): iterable
    {
        yield 'string' => [static fn(): AttributeDefinition => AttributeDefinition::of('a', ValueType::String)->nullable(false), ['type' => 'string']];
        yield 'uuid' => [static fn(): AttributeDefinition => AttributeDefinition::of('a', ValueType::String)->nullable(false)->format('uuid'), ['type' => 'string', 'format' => 'uuid']];
        yield 'decimal' => [static fn(): AttributeDefinition => AttributeDefinition::of('a', ValueType::String)->nullable(false)->format('decimal'), ['type' => 'string']];
        yield 'integer' => [static fn(): AttributeDefinition => AttributeDefinition::of('a', ValueType::Integer)->nullable(false), ['type' => 'integer']];
        yield 'number' => [static fn(): AttributeDefinition => AttributeDefinition::of('a', ValueType::Number)->nullable(false), ['type' => 'number']];
        yield 'boolean' => [static fn(): AttributeDefinition => AttributeDefinition::of('a', ValueType::Boolean)->nullable(false), ['type' => 'boolean']];
        yield 'nullable' => [static fn(): AttributeDefinition => AttributeDefinition::of('a', ValueType::Integer), ['type' => ['integer', 'null']]];
        yield 'date' => [static fn(): AttributeDefinition => AttributeDefinition::of('a', ValueType::Date)->nullable(false)->format('Y-m-d'), ['type' => 'string', 'format' => 'date']];
        yield 'date and time' => [static fn(): AttributeDefinition => AttributeDefinition::of('a', ValueType::Date)->nullable(false)->format('Y-m-d\TH:i:sP'), ['type' => 'string', 'format' => 'date-time']];
        yield 'date of unknown format' => [static fn(): AttributeDefinition => AttributeDefinition::of('a', ValueType::Date)->nullable(false)->format('H:i'), ['type' => 'string']];
        yield 'date without format' => [static fn(): AttributeDefinition => AttributeDefinition::of('a', ValueType::Date)->nullable(false), ['type' => 'string']];
        yield 'backed enum' => [static fn(): AttributeDefinition => AttributeDefinition::of('a', ValueType::Enum)->nullable(false)->enum(Status::class), ['type' => 'string', 'enum' => ['draft', 'published']]];
        yield 'nullable enum' => [static fn(): AttributeDefinition => AttributeDefinition::of('a', ValueType::Enum)->enum(Status::class), ['type' => ['string', 'null'], 'enum' => ['draft', 'published', null]]];
        yield 'unit enum' => [static fn(): AttributeDefinition => AttributeDefinition::of('a', ValueType::Enum)->nullable(false)->enum(Suit::class), ['type' => 'string', 'enum' => ['Hearts', 'Spades']]];
        yield 'enum cast by the model' => [static fn(): AttributeDefinition => AttributeDefinition::of('status', ValueType::Enum)->nullable(false), ['type' => 'string', 'enum' => ['draft', 'published']]];
        yield 'enum cast by the model, read from its source' => [static fn(): AttributeDefinition => AttributeDefinition::of('state', ValueType::Enum)->nullable(false)->source('status'), ['type' => 'string', 'enum' => ['draft', 'published']]];
        yield 'enum of unknown class' => [static fn(): AttributeDefinition => AttributeDefinition::of('title', ValueType::Enum), []];
        yield 'array' => [static fn(): AttributeDefinition => AttributeDefinition::of('a', ValueType::Array)->nullable(false), ['type' => 'array', 'items' => []]];
        yield 'typed array' => [static fn(): AttributeDefinition => AttributeDefinition::of('a', ValueType::Array)->nullable(false)->items(AttributeDefinition::of('a', ValueType::Integer)->nullable(false)), ['type' => 'array', 'items' => ['type' => 'integer']]];
        yield 'struct' => [
            static fn(): AttributeDefinition => AttributeDefinition::of('a', ValueType::Struct)->nullable(false)->children(AttributeDefinition::of('b', ValueType::String)->nullable(false), AttributeDefinition::of('c', ValueType::Struct)->nullable(false)->children(AttributeDefinition::of('d', ValueType::Boolean)->nullable(false))),
            ['type' => 'object', 'properties' => ['b' => ['type' => 'string'], 'c' => ['type' => 'object', 'properties' => ['d' => ['type' => 'boolean']]]]],
        ];
        yield 'empty struct' => [static fn(): AttributeDefinition => AttributeDefinition::of('a', ValueType::Struct)->nullable(false), ['type' => 'object']];
        yield 'mixed, nullable' => [static fn(): AttributeDefinition => AttributeDefinition::of('a', ValueType::Mixed), ['type' => 'string', 'description' => 'a']];
    }

    /**
     * @param Closure(): AttributeDefinition $definition built in the test: the provider runs without the package
     * @param array<string, mixed> $expected
     */
    #[DataProvider('valueTypes')]
    public function test_a_value_type_gives_its_schema(Closure $definition, array $expected): void
    {
        $definition = $definition();

        Schemas::assertSame($expected, $this->schemas()->schema($definition, $definition->name));
    }

    public function test_a_mixed_value_is_typed_by_the_value_rendered_at_its_path(): void
    {
        $members = $this->schemas()->members([
            'list' => AttributeDefinition::of('list', ValueType::Array)->items(AttributeDefinition::of('list', ValueType::Mixed)),
            'struct' => AttributeDefinition::of('struct', ValueType::Struct)->nullable(false)->children(AttributeDefinition::of('raw', ValueType::Mixed)),
        ], 'attributes.');

        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'list' => ['type' => ['array', 'null'], 'items' => ['type' => 'string', 'description' => 'attributes.list.0']],
                'struct' => ['type' => 'object', 'properties' => ['raw' => ['type' => 'string', 'description' => 'attributes.struct.raw']]],
            ],
        ], $members ?? Schema::create());
    }

    public function test_no_value_gives_no_member(): void
    {
        self::assertNull($this->schemas()->members([]));
    }

    public function test_the_date_formats_of_the_configuration_replace_the_defaults(): void
    {
        $document = $this->context()->document;
        $context = GenerationContext::for(new Configuration([$document->name => $document], dateFormats: ['d/m/Y' => 'date']), $document);
        $schemas = new AttributeSchemas($context, null, static fn(string $path): Schema => Schema::create());

        Schemas::assertSame(['type' => 'string', 'format' => 'date'], $schemas->schema(AttributeDefinition::of('a', ValueType::Date)->nullable(false)->format('d/m/Y'), 'a'));
        Schemas::assertSame(['type' => 'string'], $schemas->schema(AttributeDefinition::of('a', ValueType::Date)->nullable(false)->format('Y-m-d'), 'a'));
    }

    /**
     * A sample rendering every mixed value as a string described by its path.
     */
    private function schemas(): AttributeSchemas
    {
        return new AttributeSchemas($this->context(), Post::class, static fn(string $path): Schema => Schema::string()->description($path));
    }
}
