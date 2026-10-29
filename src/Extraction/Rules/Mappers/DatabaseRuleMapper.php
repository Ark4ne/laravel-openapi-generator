<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;

/**
 * `exists`, `unique` (and `Rule::exists()`, `Rule::unique()`): a sentence with the table and the column.
 */
final readonly class DatabaseRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['exists', 'unique'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $table = $rule->parameters[0] ?? '';
        $column = $rule->parameters[1] ?? null;
        $target = $column === null || $column === 'NULL' ? $table : "{$table}.{$column}";

        return $field->addConditions(($rule->name === 'exists' ? 'exists in ' : 'unique in ') . $target);
    }
}
