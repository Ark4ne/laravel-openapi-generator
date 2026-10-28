<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;

/**
 * `alpha`, `alpha_num`, `alpha_dash` (a pattern with `:ascii`, a sentence otherwise), `lowercase`, `uppercase`.
 */
final readonly class CharactersRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['alpha', 'alpha_num', 'alpha_dash', 'lowercase', 'uppercase'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $field = $field->schema(SchemaTypes::ensure($field->schema, 'string'));

        if (in_array($rule->name, ['lowercase', 'uppercase'], true)) {
            return $field->addConditions($rule->name);
        }

        return in_array('ascii', $rule->parameters, true)
            ? $field->schema($field->schema->pattern(self::ASCII[$rule->name]))
            : $field->addConditions(self::UNICODE[$rule->name]);
    }

    private const ASCII = ['alpha' => '^[a-zA-Z]+$', 'alpha_num' => '^[a-zA-Z0-9]+$', 'alpha_dash' => '^[a-zA-Z0-9_-]+$'];

    private const UNICODE = [
        'alpha' => 'letters only',
        'alpha_num' => 'letters and numbers only',
        'alpha_dash' => 'letters, numbers, dashes and underscores only',
    ];
}
