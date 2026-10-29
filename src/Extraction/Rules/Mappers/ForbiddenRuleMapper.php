<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;

/**
 * `prohibited`, `missing`: the field must not be sent; left out of the schemas. `prohibits`: a sentence.
 */
final readonly class ForbiddenRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['prohibited', 'missing', 'prohibits'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        if ($rule->name === 'prohibits') {
            return $field->addConditions('prohibits ' . SchemaTypes::list($rule->parameters, 'and'));
        }

        return $rule->conditional ? $field->addConditions("{$rule->name} in some cases") : $field->forbidden();
    }
}
