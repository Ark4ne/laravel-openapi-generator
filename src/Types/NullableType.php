<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Types;

/**
 * A type or `null`: `?T`, `T|null`.
 */
final readonly class NullableType implements Type
{
    public function __construct(
        public Type $type,
    ) {}

    public function toString(): string
    {
        return $this->type instanceof NamedType || $this->type instanceof GenericType || $this->type instanceof ListType
            ? '?' . $this->type->toString()
            : $this->type->toString() . '|null';
    }
}
