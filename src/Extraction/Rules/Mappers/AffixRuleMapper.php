<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;

/**
 * `starts_with`, `ends_with`: a pattern (a sentence when another rule set one); `doesnt_start_with`,
 * `doesnt_end_with`: a sentence.
 */
final readonly class AffixRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['starts_with', 'ends_with', 'doesnt_start_with', 'doesnt_end_with'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $field = $field->schema(SchemaTypes::ensure($field->schema, 'string'));
        $values = SchemaTypes::list($rule->parameters);

        if (str_starts_with($rule->name, 'doesnt_')) {
            return $field->addConditions(($rule->name === 'doesnt_start_with' ? 'does not start with ' : 'does not end with ') . $values);
        }

        if ($field->schema->pattern !== null) {
            return $field->addConditions(($rule->name === 'starts_with' ? 'starts with ' : 'ends with ') . $values);
        }

        $alternatives = '(?:' . implode('|', array_map(self::quote(...), $rule->parameters)) . ')';

        return $field->schema($field->schema->pattern($rule->name === 'starts_with' ? "^{$alternatives}" : "{$alternatives}$"));
    }

    /**
     * Escapes the characters an ECMA-262 pattern gives a meaning to.
     */
    private static function quote(string $value): string
    {
        return (string) preg_replace('/[.*+?^${}()|[\]\\\\\/]/', '\\\\$0', $value);
    }
}
