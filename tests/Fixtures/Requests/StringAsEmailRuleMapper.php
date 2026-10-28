<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;

/**
 * Replaces the built-in `string` mapper, to test the registration order.
 */
final class StringAsEmailRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['string'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        return $field->schema($field->schema->type('string')->format('email'));
    }
}
