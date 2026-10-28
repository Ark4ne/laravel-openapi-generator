<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;

/**
 * `numeric`: a number, unless `integer` already made it an integer.
 */
final readonly class NumericRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['numeric'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        return SchemaTypes::of($field->schema) === 'integer' ? $field : $field->schema($field->schema->type('number'));
    }
}
