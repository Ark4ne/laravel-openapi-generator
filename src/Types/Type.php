<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Types;

/**
 * A PHP type read from a native declaration or a PHPDoc tag.
 *
 * The concrete types (named, generic, union, nullable, array shape…) and the TypeResolver come with E3.
 */
interface Type
{
    /**
     * The type written as in a PHPDoc tag: `int`, `list<User>`, `?string`, `array{id: int}`.
     */
    public function toString(): string;
}
