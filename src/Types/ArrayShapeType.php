<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Types;

/**
 * An array with known keys: `array{id: int, name?: string}`, `list{int, string}`.
 */
final readonly class ArrayShapeType implements Type
{
    /**
     * @param list<ArrayShapeItem> $items
     * @param bool $sealed `false` for `array{id: int, ...}`: other keys may exist
     */
    public function __construct(
        public array $items,
        public bool $list = false,
        public bool $sealed = true,
    ) {}

    public function toString(): string
    {
        $items = array_map(static fn(ArrayShapeItem $item): string => $item->toString(), $this->items);

        if (! $this->sealed) {
            $items[] = '...';
        }

        return ($this->list ? 'list' : 'array') . '{' . implode(', ', $items) . '}';
    }
}
