<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;

/**
 * `digits`, `digits_between`: bounds of an integer, a pattern of a string; `min_digits`, `max_digits`: a sentence.
 */
final readonly class DigitsRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['digits', 'digits_between', 'min_digits', 'max_digits'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $first = (int) ($rule->parameters[0] ?? 0);
        $last = (int) ($rule->parameters[1] ?? $first);

        if ($rule->name === 'min_digits' || $rule->name === 'max_digits') {
            return $field->addConditions(($rule->name === 'min_digits' ? 'at least ' : 'at most ') . "{$first} digits");
        }

        if ($rule->name === 'digits') {
            $last = $first;
        }

        if (SchemaTypes::of($field->schema) === 'integer') {
            return $field->schema($field->schema->minimum($first > 1 ? 10 ** ($first - 1) : 0)->maximum(10 ** $last - 1));
        }

        $repeat = $first === $last ? "{{$first}}" : "{{$first},{$last}}";

        return $field->schema(SchemaTypes::ensure($field->schema, 'string')->pattern("^[0-9]{$repeat}$"));
    }
}
