<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use BackedEnum;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionProperty;

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
        $enum = self::property($rule->rule, 'type');

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

        $type = (new ReflectionEnum($enum))->getBackingType();

        return $field->schema($field->schema->type($type instanceof ReflectionNamedType && $type->getName() === 'int' ? 'integer' : 'string')->enum(...$values));
    }

    /**
     * @return list<mixed>
     */
    private static function cases(?object $rule, string $property): array
    {
        $cases = self::property($rule, $property);

        return is_array($cases) ? array_values($cases) : [];
    }

    private static function property(?object $rule, string $property): mixed
    {
        return $rule !== null && property_exists($rule, $property) ? (new ReflectionProperty($rule, $property))->getValue($rule) : null;
    }
}
