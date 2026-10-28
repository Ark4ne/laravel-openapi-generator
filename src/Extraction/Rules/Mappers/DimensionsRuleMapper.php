<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;

/**
 * `dimensions` (and `Rule::dimensions()`): a sentence.
 */
final readonly class DimensionsRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['dimensions'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        return $field->addConditions('dimensions ' . implode(', ', $rule->parameters));
    }
}
