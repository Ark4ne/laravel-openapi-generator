<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;

/**
 * `contains` (and `Rule::contains()`): the list contains these values (a sentence).
 */
final readonly class ContainsRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['contains'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        return $field->schema(SchemaTypes::ensure($field->schema, 'array'))->addConditions('contains ' . SchemaTypes::list($rule->parameters, 'and'));
    }
}
