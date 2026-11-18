<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Extraction\Strategies;

use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInQueryStrategy;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApiWriter\Enums\ParameterLocation;
use Ark4ne\OpenApiWriter\Enums\ParameterStyle;
use Ark4ne\OpenApiWriter\Enums\Version;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BuiltInQueryStrategy::class)]
final class BuiltInQueryStrategyTest extends TestCase
{
    public function test_all_flattens_every_level(): void
    {
        self::assertSame([
            'page' => ['schema' => ['type' => 'integer'], 'required' => true],
            'filter[][name][]' => ['schema' => ['type' => 'string'], 'required' => false],
            'sort[field]' => ['schema' => ['type' => 'string'], 'required' => false],
        ], $this->parameters('all'));
    }

    public function test_last_keeps_the_last_list_as_an_array(): void
    {
        self::assertSame([
            'page' => ['schema' => ['type' => 'integer'], 'required' => true],
            'filter[][name]' => ['schema' => ['type' => 'array', 'items' => ['type' => 'string']], 'required' => false],
            'sort[field]' => ['schema' => ['type' => 'string'], 'required' => false],
        ], $this->parameters('last'));
    }

    public function test_none_gives_one_parameter_per_top_level_field(): void
    {
        $parameters = $this->describe('none');

        self::assertSame(['page', 'filter', 'sort'], array_map(static fn(ParameterDescription $p): string => $p->name, $parameters));
        Schemas::assertSame(['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['name' => ['type' => 'array', 'items' => ['type' => 'string']]]]], $parameters[1]->schema);
        self::assertNull($parameters[1]->style);
        self::assertSame(ParameterStyle::DeepObject, $parameters[2]->style);
        self::assertTrue($parameters[2]->explode);
        self::assertTrue($parameters[0]->required);
    }

    public function test_querystring_describes_the_whole_query_from_3_2(): void
    {
        $parameters = $this->describe('querystring', Version::V3_2_0);

        self::assertCount(1, $parameters);
        self::assertSame(ParameterLocation::Querystring, $parameters[0]->in);
        self::assertSame(['page', 'filter', 'sort'], array_keys((array) ($parameters[0]->schema->toArray()['properties'] ?? [])));
    }

    public function test_querystring_falls_back_to_none_before_3_2(): void
    {
        self::assertSame(['page', 'filter', 'sort'], array_map(static fn(ParameterDescription $p): string => $p->name, $this->describe('querystring', Version::V3_1_2)));
    }

    public function test_an_untyped_query_field_is_a_string(): void
    {
        $parameters = (new BuiltInQueryStrategy('all'))->parameters([FieldDescription::create('q')], $this->context());

        Schemas::assertSame(['type' => 'string'], $parameters[0]->schema);
        self::assertSame(ParameterLocation::Query, $parameters[0]->in);
    }

    public function test_all_and_last_write_each_property_of_an_object_field_as_its_own_parameter(): void
    {
        $fields = FieldDescription::create('fields')->required()->schema(Schema::object()->required('posts')->additionalProperties(false)->properties(
            Schema::string()->description('Fields of `posts`.')->objectId('posts'),
            Schema::object()->properties(Schema::string()->objectId('name'))->objectId('authors'),
        ));

        foreach (['all', 'last'] as $by) {
            $parameters = (new BuiltInQueryStrategy($by))->parameters([$fields], $this->context());

            self::assertSame(['fields[posts]', 'fields[authors][name]'], array_map(static fn(ParameterDescription $p): string => $p->name, $parameters));
            Schemas::assertSame(['type' => 'string', 'description' => 'Fields of `posts`.'], $parameters[0]->schema);
            self::assertTrue($parameters[0]->required);
            self::assertFalse($parameters[1]->required);
            self::assertNull($parameters[0]->style);
        }
    }

    public function test_an_object_field_without_properties_stays_a_deep_object(): void
    {
        $parameters = (new BuiltInQueryStrategy('all'))->parameters([FieldDescription::create('fields')->schema(Schema::object())], $this->context());

        self::assertSame('fields', $parameters[0]->name);
        self::assertSame(ParameterStyle::DeepObject, $parameters[0]->style);
        self::assertTrue($parameters[0]->explode);
    }

    public function test_an_object_field_open_to_other_keys_stays_a_deep_object_with_an_info(): void
    {
        $context = $this->context();
        $open = Schema::object()->properties(Schema::string()->objectId('posts'))->additionalProperties(Schema::string());
        $parameters = (new BuiltInQueryStrategy('all'))->parameters([FieldDescription::create('fields')->schema($open)], $context);

        self::assertSame(['fields'], array_map(static fn(ParameterDescription $p): string => $p->name, $parameters));
        self::assertSame(ParameterStyle::DeepObject, $parameters[0]->style);
        self::assertDiagnostics(['[info] request: the query parameter fields accepts keys it does not name, written as a deepObject'], $context);
    }

    public function test_none_keeps_an_object_field_as_a_deep_object(): void
    {
        $fields = FieldDescription::create('fields')->schema(Schema::object()->properties(Schema::string()->objectId('posts')));
        $parameters = (new BuiltInQueryStrategy('none'))->parameters([$fields], $this->context());

        self::assertSame('fields', $parameters[0]->name);
        self::assertSame(ParameterStyle::DeepObject, $parameters[0]->style);
    }

    /**
     * @return array<string, array{schema: array<string, mixed>, required: bool}>
     */
    private function parameters(string $by): array
    {
        $parameters = [];

        foreach ($this->describe($by) as $parameter) {
            /** @var array<string, mixed> $schema */
            $schema = json_decode($parameter->schema->toJson(), true, flags: JSON_THROW_ON_ERROR);
            $parameters[$parameter->name] = ['schema' => $schema, 'required' => $parameter->required];
        }

        return $parameters;
    }

    /**
     * @return list<ParameterDescription>
     */
    private function describe(string $by, Version $version = Version::V3_1_2): array
    {
        return (new BuiltInQueryStrategy($by))->parameters([
            FieldDescription::create('page')->schema(Schema::integer())->required(),
            FieldDescription::create('filter')->schema(Schema::array()),
            FieldDescription::create('filter.*.name.*')->schema(Schema::string())->required(),
            FieldDescription::create('sort.field')->schema(Schema::string()),
        ], $this->context(version: $version));
    }
}
