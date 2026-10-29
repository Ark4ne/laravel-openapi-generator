<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;

/**
 * `same`, `different`, `in_array`, `confirmed`: a sentence naming the other field (`confirmed` adds the
 * confirmation field in the RuleParser).
 */
final readonly class FieldReferenceRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['same', 'different', 'in_array', 'confirmed'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $other = $rule->parameters[0] ?? '';

        return match ($rule->name) {
            'same' => $field->addConditions("same as {$other}"),
            'different' => $field->addConditions("different from {$other}"),
            'in_array' => $field->addConditions("one of {$other}"),
            default => $field,
        };
    }
}
