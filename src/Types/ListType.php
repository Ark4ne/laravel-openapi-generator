<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Types;

/**
 * A list of values: `list<T>`, `non-empty-list<T>`, and `T[]`, which applications use for lists.
 */
final readonly class ListType implements Type
{
    public function __construct(
        public Type $value,
        public bool $nonEmpty = false,
    ) {}

    public function toString(): string
    {
        return ($this->nonEmpty ? 'non-empty-list<' : 'list<') . $this->value->toString() . '>';
    }
}
