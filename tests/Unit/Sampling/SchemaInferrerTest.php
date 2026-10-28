<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Sampling;

use Ark4ne\OpenApi\Configuration\SamplingConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Sampling\ModelSchemas;
use Ark4ne\OpenApi\Sampling\SchemaInferrer;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Status;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Factories\ArticleFactory;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Http\Resources\ArticleResource;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Article;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Author;
use Ark4ne\OpenApi\Tests\Fixtures\Types\Signatures;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApi\Types\TypeResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;

#[CoversClass(SchemaInferrer::class)]
#[CoversClass(ModelSchemas::class)]
final class SchemaInferrerTest extends SamplingTestCase
{
    /**
     * @return iterable<string, array{mixed, string|null, string}>
     */
    public static function values(): iterable
    {
        yield 'integer' => [1, null, '{"type":"integer","example":1}'];
        yield 'number' => [1.5, null, '{"type":"number","example":1.5}'];
        yield 'string' => ['text', null, '{"type":"string","example":"text"}'];
        yield 'boolean' => [true, null, '{"type":"boolean","example":true}'];
        yield 'null without type' => [null, null, '{}'];
        yield 'null of a declared type' => [null, '?int', '{"type":["integer","null"]}'];
        yield 'declared type wins over the value' => [1, 'float', '{"type":"number","example":1}'];
        yield 'uuid' => ['9b2f4a7e-1c3d-4e5f-8a9b-0c1d2e3f4a5b', null, '{"type":"string","format":"uuid","example":"9b2f4a7e-1c3d-4e5f-8a9b-0c1d2e3f4a5b"}'];
        yield 'date of the configuration' => ['2024-01-02', null, '{"type":"string","format":"date","example":"2024-01-02"}'];
        yield 'date-time of the configuration' => ['2024-01-02 03:04:05', null, '{"type":"string","format":"date-time","example":"2024-01-02 03:04:05"}'];
        yield 'date object' => [CarbonImmutable::parse('2024-01-02 03:04:05', 'UTC'), null, '{"type":"string","format":"date-time","example":"2024-01-02T03:04:05.000000Z"}'];
        yield 'enum' => [Status::Draft, null, '{"type":"string","enum":["draft","published"],"example":"draft"}'];
        yield 'list' => [[1, 2], null, '{"type":"array","items":{"type":"integer","example":1}}'];
        yield 'empty list of a declared type' => [[], 'list<string>', '{"type":"array","items":{"type":"string"}}'];
        yield 'empty list' => [[], null, '{"type":"array"}'];
        yield 'object' => [['id' => 1, 'name' => null], null, '{"type":"object","properties":{"id":{"type":"integer","example":1},"name":{}},"required":["id","name"]}'];
        yield 'shape declared' => [['id' => 1, 'name' => null], 'array{id: int, name: ?string}', '{"type":"object","properties":{"id":{"type":"integer","example":1},"name":{"type":["string","null"]}},"required":["id","name"]}'];
        yield 'missing value' => [['id' => 1, 'posts' => new MissingValue()], null, '{"type":"object","properties":{"id":{"type":"integer","example":1},"posts":{}},"required":["id"]}'];
        yield 'collection' => [new Collection(['a']), null, '{"type":"array","items":{"type":"string","example":"a"}}'];
    }

    #[DataProvider('values')]
    public function test_a_value_gives_its_schema(mixed $value, ?string $declared, string $schema): void
    {
        Schemas::assertSame($schema, $this->inferrer()->infer($value, $this->context(), $declared === null ? null : $this->type($declared)));
    }

    public function test_the_examples_can_be_disabled(): void
    {
        $context = $this->context();
        $context = new GenerationContext(new \Ark4ne\OpenApi\Configuration\Configuration($context->configuration->documents, sampling: new SamplingConfiguration(examples: false)), $context->document);

        Schemas::assertSame('{"type":"object","properties":{"id":{"type":"integer"}},"required":["id"]}', $this->inferrer()->infer(['id' => 1], $context));
    }

    public function test_a_model_is_described_by_its_attributes_and_their_declared_types(): void
    {
        $article = ArticleFactory::new()->makeOne();
        $article->id = 1;

        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'example' => $article->title],
                'body' => ['type' => ['string', 'null']],
                'status' => ['type' => 'string', 'enum' => ['draft', 'published'], 'example' => 'published'],
                'rating' => ['type' => 'string', 'pattern' => '^-?[0-9]+\\.[0-9]{2}$', 'example' => '4.50'],
                'meta' => ['type' => 'object', 'properties' => ['views' => ['type' => 'integer', 'example' => 3]], 'required' => ['views']],
                'published_at' => ['type' => ['string', 'null'], 'format' => 'date-time', 'example' => '2024-01-02T03:04:05.000000Z'],
                'id' => ['type' => 'integer', 'example' => 1],
            ],
            'required' => ['title', 'body', 'status', 'rating', 'meta', 'published_at', 'id'],
        ], $this->inferrer()->infer($article, $this->context()));
    }

    public function test_the_declared_schemas_of_a_model_come_from_its_casts_its_properties_and_its_columns(): void
    {
        $schemas = (new ModelSchemas(new TypeResolver()))->of(new Author());

        self::assertSame(['id', 'name', 'born_at', 'active', 'score', 'tags'], array_keys($schemas));
        Schemas::assertSame(['type' => 'integer'], $schemas['id']);
        Schemas::assertSame(['type' => 'string'], $schemas['name']);
        Schemas::assertSame(['type' => ['string', 'null'], 'format' => 'date'], $schemas['born_at']);
        Schemas::assertSame(['type' => 'boolean'], $schemas['active']);
        Schemas::assertSame(['type' => 'array'], $schemas['tags']);
        Schemas::assertSame(['type' => 'number'], $schemas['score']);
    }

    public function test_a_nested_resource_becomes_a_component(): void
    {
        $context = $this->context();
        $article = new Article(['title' => 'Nested']);
        $article->id = 3;

        $schema = $this->inferrer()->infer(['post' => new ArticleResource($article)], $context);

        Schemas::assertSame('{"type":"object","properties":{"post":{"$ref":"#/components/schemas/Post"}},"required":["post"]}', $schema);
        self::assertSame('Post', $context->components->nameOf(ArticleResource::class));
        Schemas::assertSame('{"type":"object","properties":{"id":{"type":"integer","example":3},"title":{"type":"string","example":"Nested"}},"required":["id","title"]}', $context->components->get('Post') ?? \Ark4ne\OpenApiWriter\Objects\Schema::create());
    }

    private function inferrer(): SchemaInferrer
    {
        return new SchemaInferrer(new ModelSchemas(new TypeResolver()));
    }

    private function type(string $type): Type
    {
        return (new TypeResolver())->parse($type, new ReflectionClass(Signatures::class));
    }
}
