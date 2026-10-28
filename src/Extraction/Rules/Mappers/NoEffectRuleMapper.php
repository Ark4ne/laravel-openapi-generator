<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;

/**
 * `bail`, `exclude*`: they change how the request is validated, not what it accepts.
 */
final readonly class NoEffectRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['bail', 'exclude', 'exclude_if', 'exclude_unless', 'exclude_with', 'exclude_without'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        return $field;
    }
}
