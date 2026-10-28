<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;

/**
 * `after`, `after_or_equal`, `before`, `before_or_equal`, `date_equals`: a sentence (a date, or another field).
 */
final readonly class DateComparisonRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['after', 'after_or_equal', 'before', 'before_or_equal', 'date_equals'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        return $field->addConditions(self::WORDS[$rule->name] . ' ' . ($rule->parameters[0] ?? ''));
    }

    private const WORDS = [
        'after' => 'after',
        'after_or_equal' => 'after or equal to',
        'before' => 'before',
        'before_or_equal' => 'before or equal to',
        'date_equals' => 'equal to',
    ];
}
