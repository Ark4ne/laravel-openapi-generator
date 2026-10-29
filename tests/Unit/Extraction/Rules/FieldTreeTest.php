<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Extraction\Rules;

use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\FieldTree;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FieldTree::class)]
final class FieldTreeTest extends TestCase
{
    public function test_flat_fields_give_an_object_with_its_required_properties(): void
    {
        $schema = FieldTree::object([
            FieldDescription::create('name')->schema(Schema::string())->required(),
            FieldDescription::create('age')->schema(Schema::integer()),
        ]);

        Schemas::assertSame(['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'age' => ['type' => 'integer']], 'required' => ['name']], $schema);
    }

    public function test_nested_fields_give_nested_objects_and_lists(): void
    {
        $schema = FieldTree::object([
            FieldDescription::create('address')->schema(Schema::array())->required(),
            FieldDescription::create('address.city')->schema(Schema::string())->required(),
            FieldDescription::create('tags')->schema(Schema::array()->maxItems(3)),
            FieldDescription::create('tags.*')->schema(Schema::string())->distinct(),
            FieldDescription::create('items.*.id')->schema(Schema::integer())->required(),
        ]);

        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'address' => ['type' => 'object', 'properties' => ['city' => ['type' => 'string']], 'required' => ['city']],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 3, 'uniqueItems' => true],
                'items' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']]],
            ],
            'required' => ['address'],
        ], $schema);
    }

    public function test_conditions_become_the_description_and_forbidden_fields_are_left_out(): void
    {
        $schema = FieldTree::object([
            FieldDescription::create('vat')->schema(Schema::string())->conditions('required if type is company', 'unique in companies.vat'),
            FieldDescription::create('admin')->forbidden(),
        ]);

        Schemas::assertSame(['type' => 'object', 'properties' => ['vat' => ['type' => 'string', 'description' => "Required if type is company.\nUnique in companies.vat."]]], $schema);
    }

    public function test_an_object_with_declared_keys_keeps_them(): void
    {
        $schema = FieldTree::object([
            FieldDescription::create('meta')->schema(Schema::object()->properties(Schema::create('a'))->additionalProperties(false)),
            FieldDescription::create('meta.b')->schema(Schema::string()),
        ]);

        Schemas::assertSame('{"type":"object","properties":{"meta":{"type":"object","properties":{"a":{},"b":{"type":"string"}},"additionalProperties":false}}}', $schema);
    }

    public function test_no_field_gives_an_empty_object(): void
    {
        Schemas::assertSame(['type' => 'object'], FieldTree::object([]));
    }
}
