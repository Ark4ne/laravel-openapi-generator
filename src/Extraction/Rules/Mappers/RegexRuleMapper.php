<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;
use Ark4ne\OpenApi\Support\Patterns;

/**
 * `regex`: its pattern, without delimiters (a sentence when it has flags, which OpenAPI cannot write);
 * `not_regex`: a sentence.
 */
final readonly class RegexRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['regex', 'not_regex'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $expression = $rule->parameters[0] ?? '';
        $field = $field->schema(SchemaTypes::ensure($field->schema, 'string'));

        if ($rule->name === 'not_regex') {
            return $field->addConditions("does not match {$expression}");
        }

        $pattern = Patterns::ecma($expression);

        return $pattern === null ? $field->addConditions("matches {$expression}") : $field->schema($field->schema->pattern($pattern));
    }
}
