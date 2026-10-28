<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;

/**
 * `present`: the key must be sent, even empty.
 */
final readonly class PresentRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['present'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        return $field->required();
    }
}
