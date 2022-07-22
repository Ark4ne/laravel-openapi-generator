<?php

namespace Ark4ne\OpenApi\Support;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use IteratorAggregate;
use Traversable;

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @implements \ArrayAccess<TKey, TValue>
 * @implements \IteratorAggregate<TKey, TValue>
 * @implements \Illuminate\Contracts\Support\Arrayable<TKey, TValue>
 */
class ArrayInsensitive implements ArrayAccess, Arrayable, Countable, IteratorAggregate
{
    /**
     * @var TKey[]
     */
    protected array $keys;

    /**
     * @param array<TKey, TValue> $items
     */
    public function __construct(protected array $items)
    {
        $this->keys = array_combine(
            array_map('\strtolower', $keys = array_keys($this->items)),
            $keys
        );
    }

    public function toArray()
    {
        return $this->items;
    }

    /**
     * @return \IteratorAggregate<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * @param TKey $offset
     *
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->keys[strtolower($offset)]);
    }

    /**
     * @param TKey $offset
     *
     * @return TValue
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$this->keys[strtolower($offset)]] ?? null;
    }

    /**
     * @param TKey $offset
     * @param mixed $value
     *
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->items[$this->keys[strtolower($offset)]] = $value;
    }

    /**
     * @param TKey $offset
     *
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$this->keys[strtolower($offset)]]);
    }

    public function count(): int
    {
        return count($this->items);
    }
}
