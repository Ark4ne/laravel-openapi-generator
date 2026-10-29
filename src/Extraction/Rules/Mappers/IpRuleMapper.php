<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;

/**
 * `ipv4`, `ipv6` → their format; `ip` → either, in a sentence.
 */
final readonly class IpRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['ip', 'ipv4', 'ipv6'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $schema = SchemaTypes::ensure($field->schema, 'string');

        return $rule->name === 'ip' ? $field->schema($schema)->addConditions('an IP address') : $field->schema($schema->format($rule->name));
    }
}
