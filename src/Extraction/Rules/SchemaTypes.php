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
     * What a size (`min`, `max`, `size`…) counts for this field: `number`, `array`, `file` or `string`.
     */
    public static function size(FieldDescription $field): string
    {
        return match (true) {
            $field->file => 'file',
            in_array(self::of($field->schema), ['integer', 'number'], true) => 'number',
            in_array(self::of($field->schema), ['array', 'object'], true) => 'array',
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
