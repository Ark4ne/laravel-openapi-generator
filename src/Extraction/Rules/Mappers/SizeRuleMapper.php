<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;

/**
 * `min`, `max`, `between`, `size`, by what the field counts: length, value, items, or kilobytes of a file
 * (a sentence).
 */
final readonly class SizeRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['min', 'max', 'between', 'size'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $values = array_map(SchemaTypes::number(...), $rule->parameters);
        [$min, $max] = match ($rule->name) {
            'min' => [$values[0] ?? null, null],
            'max' => [null, $values[0] ?? null],
            'size' => [$values[0] ?? null, $values[0] ?? null],
            default => [$values[0] ?? null, $values[1] ?? null],
        };

        return self::bounds($field, $min, $max);
    }

    public static function bounds(FieldDescription $field, int|float|null $min, int|float|null $max): FieldDescription
    {
        $schema = $field->schema;

        return match (SchemaTypes::size($field)) {
            'number' => $field->schema($schema->minimum($min ?? $schema->minimum)->maximum($max ?? $schema->maximum)),
            'array' => $field->schema($schema->minItems(self::int($min) ?? $schema->minItems)->maxItems(self::int($max) ?? $schema->maxItems)),
            'object' => $field->schema($schema->minProperties(self::int($min) ?? $schema->minProperties)->maxProperties(self::int($max) ?? $schema->maxProperties)),
            'string' => $field->schema($schema->minLength(self::int($min) ?? $schema->minLength)->maxLength(self::int($max) ?? $schema->maxLength)),
            default => self::kilobytes($field, $min, $max),
        };
    }

    private static function kilobytes(FieldDescription $field, int|float|null $min, int|float|null $max): FieldDescription
    {
        return match (true) {
            $min !== null && $max !== null && $min !== $max => $field->addConditions("between {$min} and {$max} KB"),
            $min !== null && $max !== null => $field->addConditions("exactly {$min} KB"),
            $min !== null => $field->addConditions("at least {$min} KB"),
            $max !== null => $field->addConditions("at most {$max} KB"),
            default => $field,
        };
    }

    private static function int(int|float|null $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
