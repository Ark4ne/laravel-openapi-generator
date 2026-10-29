<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;

/**
 * Translates one validation rule into constraints of a field (Strategy): one class per rule.
 */
interface RuleMapper
{
    /**
     * @return list<string> rule names (`max`, `required_if`) and rule classes (`Illuminate\Validation\Rules\Enum`)
     */
    public function handles(): array;

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription;
}
