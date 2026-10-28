<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Support\Enums;
use Ark4ne\OpenApi\Support\Properties;
use BackedEnum;

/**
 * `Rule::enum()`: the values of the enum, `only()` and `except()` applied.
 */
final readonly class EnumRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['Illuminate\Validation\Rules\Enum'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $enum = Properties::read($rule->rule, 'type');

        if (! is_string($enum) || ! is_subclass_of($enum, BackedEnum::class)) {
            return $field;
        }

        $only = self::cases($rule->rule, 'only');
        $except = self::cases($rule->rule, 'except');
        $values = [];

        foreach ($enum::cases() as $case) {
            if (($only === [] || in_array($case, $only, true)) && ! in_array($case, $except, true)) {
                $values[] = $case->value;
            }
        }

        return $field->schema($field->schema->type(Enums::type($enum))->enum(...$values));
    }

    /**
     * @return list<mixed>
     */
    private static function cases(?object $rule, string $property): array
    {
        $cases = Properties::read($rule, $property);

        return is_array($cases) ? array_values($cases) : [];
    }
}
