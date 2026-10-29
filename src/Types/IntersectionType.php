<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Types;

/**
 * `A&B`.
 */
final readonly class IntersectionType implements Type
{
    /**
     * @param list<Type> $types at least two
     */
    public function __construct(
        public array $types,
    ) {}

    public function toString(): string
    {
        return implode('&', array_map(static fn(Type $type): string => $type->toString(), $this->types));
    }
}
