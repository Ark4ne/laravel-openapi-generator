<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules;

use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * Assembles fields (`address`, `address.city`, `tags.*`, `items.*.id`) into one object schema.
 *
 * - A field with named children is an object: its children are its properties, required when the child is.
 * - A field with a `*` child is a list of it; a distinct child gives `uniqueItems`.
 * - The conditions of a field become its description; forbidden fields are left out.
 */
final class FieldTree
{
    /**
     * @param list<FieldDescription> $fields
     */
    public static function object(array $fields): Schema
    {
        return self::properties(Schema::object(), self::tree($fields)->children);
    }

    /**
     * Fields of the top level, each with its whole subtree: for the query parameters of the `none` mode.
     *
     * @param list<FieldDescription> $fields
     *
     * @return array<string, array{schema: Schema, required: bool}>
     */
    public static function top(array $fields): array
    {
        $top = [];

        foreach (self::tree($fields)->children as $name => $node) {
            $top[$name] = ['schema' => self::schema($node), 'required' => $node->required()];
        }

        return $top;
    }

    /**
     * The schema of one field alone, its conditions as description.
     */
    public static function leaf(FieldDescription $field): Schema
    {
        $node = new FieldNode();
        $node->field = $field;

        return self::schema($node);
    }

    /**
     * @param list<FieldDescription> $fields
     */
    private static function tree(array $fields): FieldNode
    {
        $root = new FieldNode();

        foreach ($fields as $field) {
            if (! $field->forbidden) {
                $root->insert(explode('.', $field->name), $field);
            }
        }

        return $root;
    }

    private static function schema(FieldNode $node): Schema
    {
        $field = $node->field;
        $schema = $field === null ? Schema::create() : $field->schema;
        $children = $node->children;

        if (isset($children['*'])) {
            $item = $children['*'];
            $schema = $schema->type('array')->items(self::schema($item));
            $schema = $item->field !== null && $item->field->distinct ? $schema->uniqueItems() : $schema;
            unset($children['*']);
        }

        if ($children !== []) {
            $schema = self::properties($schema->type('object'), $children);
        }

        if ($field !== null && $field->conditions !== []) {
            $sentences = array_map(static fn(string $condition): string => ucfirst($condition) . '.', $field->conditions);
            $schema = $schema->description(implode("\n", $sentences));
        }

        return $schema;
    }

    /**
     * The children as properties of the schema; a property it already declares (`array:a,b`) is replaced in place.
     *
     * @param array<string, FieldNode> $children
     */
    private static function properties(Schema $schema, array $children): Schema
    {
        $properties = $schema->properties ?? [];
        $required = [];

        foreach ($children as $name => $child) {
            $properties[$name] = self::schema($child)->objectId($name);

            if ($child->required()) {
                $required[] = $name;
            }
        }

        $schema = $properties === [] ? $schema : $schema->properties(...array_values($properties));

        $required = array_values(array_unique([...$schema->required ?? [], ...$required]));

        return $required === [] ? $schema : $schema->required(...$required);
    }
}
