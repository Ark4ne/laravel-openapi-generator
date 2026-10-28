<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;

/**
 * `required_array_keys`: an object with these required keys.
 */
final readonly class RequiredArrayKeysRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['required_array_keys'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        return $field->schema($field->schema->type('object')->required(...$rule->parameters));
    }
}
