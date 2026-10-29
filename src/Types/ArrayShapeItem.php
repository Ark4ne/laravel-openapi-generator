<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Types;

/**
 * One key of an array shape: `id: int`, `name?: string`, or a positional item (`list{int, string}`).
 */
final readonly class ArrayShapeItem
{
    public function __construct(
        public int|string|null $key,
        public Type $type,
        public bool $optional = false,
    ) {}

    public function toString(): string
    {
        if ($this->key === null) {
            return $this->type->toString();
        }

        return $this->key . ($this->optional ? '?' : '') . ': ' . $this->type->toString();
    }
}
