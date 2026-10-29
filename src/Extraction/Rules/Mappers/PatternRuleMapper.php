<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;

/**
 * `mac_address`, `hex_color`, `ascii`: their pattern; `timezone`: a sentence.
 */
final readonly class PatternRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['mac_address', 'hex_color', 'ascii', 'timezone'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $schema = SchemaTypes::ensure($field->schema, 'string');

        return match ($rule->name) {
            'timezone' => $field->schema($schema)->addConditions('a timezone identifier'),
            default => $field->schema($schema->pattern(self::PATTERNS[$rule->name] ?? '')),
        };
    }

    private const PATTERNS = [
        'mac_address' => '^([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$',
        'hex_color' => '^#([0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$',
        'ascii' => '^[\\x00-\\x7F]*$',
    ];
}
