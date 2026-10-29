<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * `array`, `list`; `array:a,b` (and `Rule::array([…])`): an object with only these keys.
 */
final readonly class ArrayRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['array', 'list'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        if ($rule->name === 'list' || $rule->parameters === []) {
            return $field->schema($field->schema->type('array'));
        }

        return $field->schema($field->schema
            ->type('object')
            ->properties(...array_map(static fn(string $key): Schema => Schema::create($key), $rule->parameters))
            ->additionalProperties(false));
    }
}
