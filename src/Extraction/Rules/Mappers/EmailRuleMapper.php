<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;

/**
 * `email` (with its validators) and `Rule::email()`.
 */
final readonly class EmailRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['email', 'Illuminate\Validation\Rules\Email'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        return $field->schema(SchemaTypes::ensure($field->schema, 'string')->format('email'));
    }
}
