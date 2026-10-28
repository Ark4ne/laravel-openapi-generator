<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Types;

/**
 * `A|B`, without `null`: a nullable union is a NullableType of a UnionType.
 */
final readonly class UnionType implements Type
{
    /**
     * @param list<Type> $types at least two, none of them `null`
     */
    public function __construct(
        public array $types,
    ) {}

    public function toString(): string
    {
        return implode('|', array_map(static fn(Type $type): string => $type instanceof IntersectionType ? "({$type->toString()})" : $type->toString(), $this->types));
    }
}
