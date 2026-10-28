<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * `in`, `not_in` (and `Rule::in()`, `Rule::notIn()`): `enum`, or `not: {enum}`; integers when the field is one.
 */
final readonly class InRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['in', 'not_in'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $values = SchemaTypes::of($field->schema) === 'integer' || SchemaTypes::of($field->schema) === 'number'
            ? array_map(static fn(string $value): int|float|string => SchemaTypes::number($value) ?? $value, $rule->parameters)
            : $rule->parameters;

        $constraint = static fn(Schema $schema): Schema => $rule->name === 'in' ? $schema->enum(...$values) : $schema->not(Schema::create()->enum(...$values));

        // Laravel checks each element of an array against the list.
        if (SchemaTypes::of($field->schema) === 'array') {
            $items = $field->schema->items;

            return $field->schema($field->schema->items($constraint($items instanceof Schema ? $items : Schema::create())));
        }

        return $field->schema($constraint($field->schema));
    }
}
