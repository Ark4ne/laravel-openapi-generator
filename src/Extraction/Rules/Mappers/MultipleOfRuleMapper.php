<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;

/**
 * `multiple_of`.
 */
final readonly class MultipleOfRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['multiple_of'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $value = SchemaTypes::number($rule->parameters[0] ?? null);

        return $value === null ? $field : $field->schema(SchemaTypes::ensure($field->schema, 'number')->multipleOf($value));
    }
}
