<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;

/**
 * `filled`: not empty when sent.
 */
final readonly class FilledRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['filled'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        return match (SchemaTypes::size($field)) {
            'array' => $field->schema($field->schema->minItems(1)),
            'number', 'file' => $field,
            default => $field->schema($field->schema->minLength(1)),
        };
    }
}
