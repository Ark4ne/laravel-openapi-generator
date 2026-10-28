<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;

/**
 * `uuid` → `format: uuid`, `ulid` → its pattern.
 */
final readonly class IdentifierRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['uuid', 'ulid'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $schema = SchemaTypes::ensure($field->schema, 'string');

        return $field->schema($rule->name === 'uuid' ? $schema->format('uuid') : $schema->pattern(self::ULID));
    }

    public const ULID = '^[0-7][0-9a-hjkmnp-tv-zA-HJKMNP-TV-Z]{25}$';
}
