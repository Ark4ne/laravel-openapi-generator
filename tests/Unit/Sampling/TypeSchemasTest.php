<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Sampling;

use Ark4ne\OpenApi\Sampling\TypeSchemas;
use Ark4ne\OpenApi\Tests\Fixtures\Types\Signatures;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Types\TypeResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(TypeSchemas::class)]
final class TypeSchemasTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function types(): iterable
    {
        yield 'int' => ['int', '{"type":"integer"}'];
        yield 'float' => ['float', '{"type":"number"}'];
        yield 'string' => ['string', '{"type":"string"}'];
        yield 'non-empty-string' => ['non-empty-string', '{"type":"string","minLength":1}'];
        yield 'bool' => ['bool', '{"type":"boolean"}'];
        yield 'null' => ['null', '{"type":"null"}'];
        yield 'nullable' => ['?int', '{"type":["integer","null"]}'];
        yield 'mixed' => ['mixed', '{}'];
        yield 'positive-int' => ['positive-int', '{"type":"integer","minimum":1}'];
        yield 'int range' => ['int<0, 10>', '{"type":"integer","minimum":0,"maximum":10}'];
        yield 'list' => ['list<string>', '{"type":"array","items":{"type":"string"}}'];
        yield 'non-empty-list' => ['non-empty-list<int>', '{"type":"array","items":{"type":"integer"},"minItems":1}'];
        yield 'brackets' => ['int[]', '{"type":"array","items":{"type":"integer"}}'];
        yield 'map' => ['array<string, int>', '{"type":"object","additionalProperties":{"type":"integer"}}'];
        yield 'int keyed array' => ['array<int, string>', '{"type":"array","items":{"type":"string"}}'];
        yield 'plain array' => ['array', '{"type":"array"}'];
        yield 'shape' => ['array{id: int, name?: string}', '{"type":"object","properties":{"id":{"type":"integer"},"name":{"type":"string"}},"required":["id"],"additionalProperties":false}'];
        yield 'unsealed shape' => ['array{id: int, ...}', '{"type":"object","properties":{"id":{"type":"integer"}},"required":["id"]}'];
        yield 'list shape' => ['list{int, string}', '{"type":"array","prefixItems":[{"type":"integer"},{"type":"string"}],"minItems":2,"maxItems":2}'];
        yield 'literals' => ["'draft'|'published'", '{"type":"string","enum":["draft","published"]}'];
        yield 'literals of several types' => ["'auto'|1|true", '{"enum":["auto",1,true]}'];
        yield 'literal' => ['1', '{"type":"integer","const":1}'];
        yield 'union' => ['int|string', '{"anyOf":[{"type":"integer"},{"type":"string"}]}'];
        yield 'enum' => ['\Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Status', '{"type":"string","enum":["draft","published"]}'];
        yield 'date' => ['\DateTimeInterface', '{"type":"string","format":"date-time"}'];
        yield 'collection' => ['\Illuminate\Support\Collection<int, string>', '{"type":"array","items":{"type":"string"}}'];
        yield 'class' => ['\Ark4ne\OpenApi\Tests\Fixtures\Types\Sibling', '{"type":"object"}'];
        yield 'object' => ['object', '{"type":"object"}'];
        yield 'intersection' => ['\Countable&\Traversable', '{"allOf":[{"type":"object"},{"type":"object"}]}'];
    }

    #[DataProvider('types')]
    public function test_a_type_gives_its_schema(string $type, string $json): void
    {
        $resolved = (new TypeResolver())->parse($type, new ReflectionClass(Signatures::class));

        Schemas::assertSame($json, TypeSchemas::of($resolved));
    }
}
