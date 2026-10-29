<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;

/**
 * `required_if`, `required_unless`, `required_with*`, `required_without*`, `required_if_accepted`,
 * `required_if_declined`, and the same conditions of `present_*`, `missing_*`, `prohibited_*`, `accepted_if`,
 * `declined_if`: a sentence of the description, the field stays optional.
 */
final readonly class ConditionalRequiredRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['required_if', 'required_unless', 'required_with', 'required_with_all', 'required_without', 'required_without_all', 'required_if_accepted', 'required_if_declined', 'present_if', 'present_unless', 'present_with', 'present_with_all', 'missing_if', 'missing_unless', 'missing_with', 'missing_with_all', 'prohibited_if', 'prohibited_unless', 'prohibited_if_accepted', 'prohibited_if_declined', 'accepted_if', 'declined_if'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $verb = explode('_', $rule->name)[0];
        $kind = substr($rule->name, strlen($verb) + 1);
        $parameters = $rule->parameters;

        return $field->addConditions(match ($kind) {
            'if' => sprintf('%s if %s is %s', $verb, $parameters[0] ?? '', SchemaTypes::list(array_slice($parameters, 1))),
            'unless' => sprintf('%s unless %s is %s', $verb, $parameters[0] ?? '', SchemaTypes::list(array_slice($parameters, 1))),
            'with' => "{$verb} with " . SchemaTypes::list($parameters),
            'with_all' => "{$verb} with " . SchemaTypes::list($parameters, 'and'),
            'without' => "{$verb} without " . SchemaTypes::list($parameters),
            'without_all' => "{$verb} without " . SchemaTypes::list($parameters, 'and'),
            'if_accepted' => sprintf('%s if %s is accepted', $verb, $parameters[0] ?? ''),
            default => sprintf('%s if %s is declined', $verb, $parameters[0] ?? ''),
        });
    }
}
