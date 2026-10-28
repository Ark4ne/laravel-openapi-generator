<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Support;

use BackedEnum;
use ReflectionEnum;
use ReflectionNamedType;

/**
 * Backed enums as schemas see them: their values and the JSON type of those values.
 */
final class Enums
{
    /**
     * @param class-string<BackedEnum> $enum
     *
     * @return list<int|string>
     */
    public static function values(string $enum): array
    {
        return array_map(static fn(BackedEnum $case): int|string => $case->value, $enum::cases());
    }

    /**
     * `integer` for an int-backed enum, `string` otherwise.
     *
     * @param class-string<BackedEnum> $enum
     */
    public static function type(string $enum): string
    {
        $type = (new ReflectionEnum($enum))->getBackingType();

        return $type instanceof ReflectionNamedType && $type->getName() === 'int' ? 'integer' : 'string';
    }
}
