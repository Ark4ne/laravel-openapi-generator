<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Extraction\Rules;

use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaTypes::class)]
final class SchemaTypesTest extends TestCase
{
    /**
     * @return iterable<string, array{Schema, array<string, mixed>}>
     */
    public static function enums(): iterable
    {
        yield 'strings' => [Schema::create()->enum('a', 'b'), ['type' => 'string', 'enum' => ['a', 'b']]];
        yield 'integers' => [Schema::create()->enum(1, 2), ['type' => 'integer', 'enum' => [1, 2]]];
        yield 'integers and floats' => [Schema::create()->enum(1, 2.5), ['type' => 'number', 'enum' => [1, 2.5]]];
        yield 'booleans' => [Schema::create()->enum(true, false), ['type' => 'boolean', 'enum' => [true, false]]];
        yield 'null left to the nullability' => [Schema::create()->enum('a', null), ['type' => 'string', 'enum' => ['a', null]]];
        yield 'the null type kept' => [Schema::create()->type('null')->enum('a', null), ['type' => ['null', 'string'], 'enum' => ['a', null]]];
        yield 'mixed values stay untyped' => [Schema::create()->enum('yes', 1, true), ['enum' => ['yes', 1, true]]];
        yield 'other values stay untyped' => [Schema::create()->enum(['a']), ['enum' => [['a']]]];
        yield 'a typed enum is kept' => [Schema::integer()->enum('1'), ['type' => 'integer', 'enum' => ['1']]];
        yield 'no enum' => [Schema::create(), []];
        yield 'the items of a list' => [Schema::array()->items(Schema::create()->enum('a')), ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['a']]]];
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('enums')]
    public function test_an_untyped_enum_is_typed_by_its_values(Schema $schema, array $expected): void
    {
        Schemas::assertSame($expected, SchemaTypes::enumerated($schema));
    }
}
