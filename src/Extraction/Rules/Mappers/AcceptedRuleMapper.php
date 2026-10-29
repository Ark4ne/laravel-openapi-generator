<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;

/**
 * `accepted`, `declined`: the values Laravel accepts; the field must be sent.
 */
final readonly class AcceptedRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['accepted', 'declined'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        return $field->required()->schema($field->schema->enum(...($rule->name === 'accepted' ? self::ACCEPTED : self::DECLINED)));
    }

    public const ACCEPTED = ['yes', 'on', 1, '1', true, 'true'];

    public const DECLINED = ['no', 'off', 0, '0', false, 'false'];
}
