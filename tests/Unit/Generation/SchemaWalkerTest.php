<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Generation;

use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Generation\SchemaWalker;
use Ark4ne\OpenApi\Tests\Support\Descriptions;
use Ark4ne\OpenApiWriter\Contracts\SchemaContract;
use Ark4ne\OpenApiWriter\Objects\Discriminator;
use Ark4ne\OpenApiWriter\Objects\Reference;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaWalker::class)]
final class SchemaWalkerTest extends TestCase
{
    public function test_a_reference_to_a_component_schema_names_it(): void
    {
        self::assertSame('User', SchemaWalker::component(Schema::ref('User')));
    }

    public function test_any_other_schema_names_no_component(): void
    {
        self::assertNull(SchemaWalker::component(Schema::object()));
        self::assertNull(SchemaWalker::component(Schema::ref('https://example.com/user.json')));
        self::assertNull(SchemaWalker::component(Schema::ref('#/components/responses/Error')));
    }

    public function test_a_reference_object_to_a_component_schema_names_it(): void
    {
        self::assertSame('User', SchemaWalker::component(Reference::to('#/components/schemas/User')));
    }

    public function test_the_components_a_discriminator_maps_to_are_named(): void
    {
        $schema = Schema::create()->oneOf(Schema::ref('Cat'), Schema::ref('Dog'))->discriminator(Discriminator::create()->propertyName('type')->mapping([
            'cat' => '#/components/schemas/Cat',
            'dog' => 'Dog',
            'cow' => 'https://example.com/cow.json',
            'kitten' => '#/components/schemas/Cat',
        ])->defaultMapping('Animal'));

        self::assertSame(['Cat', 'Dog', 'Animal'], SchemaWalker::discriminated($schema));
    }

    public function test_a_schema_without_discriminator_maps_to_no_component(): void
    {
        self::assertSame([], SchemaWalker::discriminated(Schema::object()));
        self::assertSame([], SchemaWalker::discriminated(Reference::to('#/components/schemas/User')));
    }

    public function test_every_child_of_a_schema_is_mapped(): void
    {
        $mapped = SchemaWalker::schema(self::everyKeyword(), static fn(Schema $schema): Schema => ($name = SchemaWalker::component($schema)) === null ? $schema : Schema::ref("{$name}2"));

        self::assertSame(array_map(static fn(string $name): string => "{$name}2", self::KEYWORDS), self::components($mapped));
    }

    public function test_a_mapped_child_of_a_keyed_keyword_keeps_its_key(): void
    {
        $mapped = SchemaWalker::schema(self::everyKeyword(), static fn(Schema $schema): Schema => SchemaWalker::component($schema) === null ? $schema : Schema::ref('Other'));

        self::assertSame(['p'], array_keys($mapped->properties ?? []));
        self::assertSame(['^x'], array_keys($mapped->patternProperties ?? []));
        self::assertSame(['p'], array_keys($mapped->dependentSchemas ?? []));
        self::assertSame(['d'], array_keys($mapped->defs ?? []));
    }

    public function test_every_child_of_a_schema_is_visited(): void
    {
        self::assertSame(self::KEYWORDS, self::components(self::everyKeyword()));
    }

    public function test_reference_objects_are_visited(): void
    {
        self::assertSame(['Referenced'], self::components(Schema::create()->allOf(Reference::to('#/components/schemas/Referenced'))));
    }

    public function test_the_path_parameters_of_a_document_are_mapped_and_visited(): void
    {
        $document = DocumentDescription::create()->pathParameters('/users/{status}', ParameterDescription::path('status', Schema::ref('Status')));

        $mapped = SchemaWalker::document($document, static fn(Schema $schema): Schema => SchemaWalker::component($schema) === null ? $schema : Schema::ref('Renamed'));

        self::assertSame(['Renamed'], self::documentComponents($mapped));
    }

    public function test_every_schema_of_a_document_is_visited(): void
    {
        $operation = Descriptions::operation()->responses();
        $operation = $operation->request($operation->request->parameters(ParameterDescription::query('q', Schema::ref('Query'))));
        $document = Descriptions::document($operation)
            ->webhooks(Descriptions::operation()->webhook('hook')->request($operation->request->parameters(ParameterDescription::header('h', Schema::ref('Webhook')))))
            ->schemas(['Component' => Schema::ref('Nested')]);

        self::assertSame(['Query', 'Webhook', 'Nested'], self::documentComponents($document));
    }

    private const array KEYWORDS = [
        'Property', 'Pattern', 'Dependent', 'Def', 'Additional', 'Names', 'UnevaluatedProperties', 'Items', 'Prefix',
        'Contains', 'UnevaluatedItems', 'AllOf', 'AnyOf', 'OneOf', 'Not', 'If', 'Then', 'Else', 'Content',
    ];

    private static function everyKeyword(): Schema
    {
        return Schema::object()
            ->properties(Schema::ref('Property')->objectId('p'))
            ->patternProperties(Schema::ref('Pattern')->objectId('^x'))
            ->dependentSchemas(Schema::ref('Dependent')->objectId('p'))
            ->defs(Schema::ref('Def')->objectId('d'))
            ->additionalProperties(Schema::ref('Additional'))
            ->propertyNames(Schema::ref('Names'))
            ->unevaluatedProperties(Schema::ref('UnevaluatedProperties'))
            ->items(Schema::ref('Items'))
            ->prefixItems(Schema::ref('Prefix'))
            ->contains(Schema::ref('Contains'))
            ->unevaluatedItems(Schema::ref('UnevaluatedItems'))
            ->allOf(Schema::ref('AllOf'))
            ->anyOf(Schema::ref('AnyOf'))
            ->oneOf(Schema::ref('OneOf'))
            ->not(Schema::ref('Not'))
            ->if(Schema::ref('If'))
            ->then(Schema::ref('Then'))
            ->else(Schema::ref('Else'))
            ->contentSchema(Schema::ref('Content'));
    }

    /**
     * @return list<string> the components referenced in `$schema`, in the order visited
     */
    private static function components(Schema $schema): array
    {
        $names = [];
        SchemaWalker::visitSchema($schema, static function (SchemaContract $child) use (&$names): void {
            $name = SchemaWalker::component($child);

            if ($name !== null) {
                $names[] = $name;
            }
        });

        return $names;
    }

    /**
     * @return list<string> the components referenced in `$document`, in the order visited
     */
    private static function documentComponents(DocumentDescription $document): array
    {
        $names = [];
        SchemaWalker::visit($document, static function (SchemaContract $child) use (&$names): void {
            $name = SchemaWalker::component($child);

            if ($name !== null) {
                $names[] = $name;
            }
        });

        return $names;
    }
}
