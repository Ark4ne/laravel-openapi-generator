<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Types;

/**
 * A PHP type read from a native declaration or a PHPDoc tag, names resolved.
 *
 * Implementations: NamedType, GenericType, ListType, ArrayShapeType, UnionType, IntersectionType, NullableType,
 * LiteralType, MixedType. They are built by the TypeResolver.
 */
interface Type
{
    /**
     * The type written as in a PHPDoc tag, with fully qualified class names: `int`, `list<App\User>`, `?string`,
     * `array{id: int}`.
     */
    public function toString(): string;
}
