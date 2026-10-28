<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;

/**
 * `required`: the field must be sent; only "in some cases" when the rule is conditional.
 */
final readonly class RequiredRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['required'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        return $rule->conditional ? $field->addConditions('required in some cases') : $field->required();
    }
}
