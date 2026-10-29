<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;

/**
 * `gt`, `gte`, `lt`, `lte`: exclusive or inclusive bounds by what the field counts; compared with another field,
 * a sentence.
 */
final readonly class ComparisonRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['gt', 'gte', 'lt', 'lte'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $value = SchemaTypes::number($rule->parameters[0] ?? null);

        if ($value === null) {
            return $field->addConditions(self::WORDS[$rule->name] . ' ' . ($rule->parameters[0] ?? ''));
        }

        if (SchemaTypes::size($field) === 'number') {
            $schema = $field->schema;

            return $field->schema(match ($rule->name) {
                'gt' => $schema->exclusiveMinimum($value),
                'gte' => $schema->minimum($value),
                'lt' => $schema->exclusiveMaximum($value),
                default => $schema->maximum($value),
            });
        }

        // A length or a count is an integer: `> 3` is `>= 4`.
        return match ($rule->name) {
            'gt' => SizeRuleMapper::bounds($field, (int) $value + 1, null),
            'gte' => SizeRuleMapper::bounds($field, $value, null),
            'lt' => SizeRuleMapper::bounds($field, null, (int) $value - 1),
            default => SizeRuleMapper::bounds($field, null, $value),
        };
    }

    private const WORDS = ['gt' => 'greater than', 'gte' => 'greater than or equal to', 'lt' => 'less than', 'lte' => 'less than or equal to'];
}
