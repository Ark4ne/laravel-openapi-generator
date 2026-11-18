<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules;

use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApiWriter\Enums\SchemaType;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * What the rule mappers read of the schema the previous rules built.
 */
final class SchemaTypes
{
    /**
     * The type set by the type rules (`string`, `integer`…), `null` when none is.
     */
    public static function of(Schema $schema): ?string
    {
        foreach ($schema->type ?? [] as $type) {
            if ($type !== SchemaType::Null) {
                return $type->value;
            }
        }

        return null;
    }

    /**
     * The schema with this type when it has none yet.
     */
    public static function ensure(Schema $schema, string $type): Schema
    {
        return self::of($schema) === null ? $schema->type($type) : $schema;
    }

    /**
     * An `enum` without type (`in`, `Rule::in()`, on the field or the items of its list) typed by its values when they
     * share one: `string`, `integer`, `number` (integers and floats) or `boolean`; `null` among them is left to the
     * nullability of the field.
     */
    public static function enumerated(Schema $schema): Schema
    {
        if (self::of($schema) === 'array' && $schema->items instanceof Schema) {
            return $schema->items(self::enumerated($schema->items));
        }

        if (self::of($schema) !== null || $schema->enum === null) {
            return $schema;
        }

        $types = [];

        foreach ($schema->enum as $value) {
            $type = match (true) {
                $value === null => null,
                is_string($value) => 'string',
                is_int($value) => 'integer',
                is_float($value) => 'number',
                is_bool($value) => 'boolean',
                default => 'unknown',
            };

            if ($type !== null) {
                $types[$type] = true;
            }
        }

        $type = match (true) {
            array_keys($types) === ['string'], array_keys($types) === ['integer'], array_keys($types) === ['number'], array_keys($types) === ['boolean'] => array_key_first($types),
            count($types) === 2 && isset($types['integer'], $types['number']) => 'number',
            default => null,
        };

        if ($type === null) {
            return $schema;
        }

        return $schema->type === null ? $schema->type($type) : $schema->addType($type);
    }

    /**
     * What a size (`min`, `max`, `size`…) counts for this field: `number`, `array`, `object`, `file` or `string`.
     */
    public static function size(FieldDescription $field): string
    {
        return match (true) {
            $field->file => 'file',
            in_array(self::of($field->schema), ['integer', 'number'], true) => 'number',
            self::of($field->schema) === 'array' => 'array',
            self::of($field->schema) === 'object' => 'object',
            default => 'string',
        };
    }

    /**
     * `1` → 1, `1.5` → 1.5; `null` for a value that is not a number (another field's name).
     */
    public static function number(?string $value): int|float|null
    {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        return str_contains($value, '.') || str_contains(strtolower($value), 'e') ? (float) $value : (int) $value;
    }

    /**
     * `a, b or c` / `a, b and c`.
     *
     * @param list<string> $values
     */
    public static function list(array $values, string $last = 'or'): string
    {
        if (count($values) < 2) {
            return implode('', $values);
        }

        $tail = array_pop($values);

        return implode(', ', $values) . " {$last} {$tail}";
    }
}
