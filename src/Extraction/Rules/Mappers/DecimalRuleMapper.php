<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;

/**
 * `decimal:min,max`: a number with that many decimal places (a sentence).
 */
final readonly class DecimalRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['decimal'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $places = count($rule->parameters) > 1 ? "{$rule->parameters[0]} to {$rule->parameters[1]}" : ($rule->parameters[0] ?? '');

        return $field->schema($field->schema->type('number'))->addConditions("{$places} decimal places");
    }
}
