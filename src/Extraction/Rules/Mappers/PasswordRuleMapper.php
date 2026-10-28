<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Rules\Mappers;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use ReflectionProperty;

/**
 * `Password` (and `Password::defaults()`): `format: password`, its lengths, its requirements in sentences.
 */
final readonly class PasswordRuleMapper implements RuleMapper
{
    public function handles(): array
    {
        return ['Illuminate\Validation\Rules\Password'];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $password = $rule->rule;
        $schema = $field->schema->type('string')->format('password');

        if ($password === null) {
            return $field->schema($schema);
        }

        $min = self::property($password, 'min');
        $max = self::property($password, 'max');
        $field = $field->schema($schema->minLength(is_int($min) ? $min : null)->maxLength(is_int($max) ? $max : null));

        foreach (self::REQUIREMENTS as $property => $condition) {
            if (self::property($password, $property) === true) {
                $field = $field->addConditions($condition);
            }
        }

        return $field;
    }

    private const REQUIREMENTS = [
        'letters' => 'letters',
        'mixedCase' => 'mixed case',
        'numbers' => 'numbers',
        'symbols' => 'symbols',
        'uncompromised' => 'not compromised',
    ];

    private static function property(object $rule, string $property): mixed
    {
        return property_exists($rule, $property) ? (new ReflectionProperty($rule, $property))->getValue($rule) : null;
    }
}
