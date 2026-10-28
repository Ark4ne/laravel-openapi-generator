<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;

/**
 * `url` (`url:https` gives its schemes in a sentence), `active_url`.
 */
final readonly class UrlRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['url', 'active_url'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $field = $field->schema(SchemaTypes::ensure($field->schema, 'string')->format('uri'));

        return $rule->parameters === [] ? $field : $field->addConditions('scheme ' . SchemaTypes::list($rule->parameters));
    }
}
