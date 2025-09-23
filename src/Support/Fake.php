<?php

namespace Ark4ne\OpenApi\Support;

use Ark4ne\OpenApi\Support\Facades\Logger;
use ArrayAccess;
use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use IteratorAggregate;
use JsonSerializable;
use ReflectionException;
use Traversable;

class Fake implements Arrayable, ArrayAccess, Countable, IteratorAggregate, Jsonable, JsonSerializable
{
    private ?\ReflectionClass $class;

    public function __construct(
        protected null|string $for = null,
        protected array $attributes = [],
    ) {
        try {
            $this->class = $this->for
                ? Reflection::reflection($this->for)
                : null;
        } catch (ReflectionException $e) {
            $this->class = null;
            Logger::error("Failed to reflect {$this->for}: " . $e->getMessage());
        }
    }

    public function __get(string $name): mixed
    {
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }

        try {
            if ($this->for) {
                return $this->fakeValue(Reflection::getPropertyType($this->for, $name)?->getType());
            }
        } catch (\Throwable $e) {
        }

        return new self;
    }

    public function __isset(string $name)
    {
        return isset($this->attributes[$name]);
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    /**
     * @param string       $name
     * @param array<mixed> $args
     *
     * @return self
     */
    public function __call(string $name, array $args): self
    {
        try {
            if ($this->class) {
                $type = Reflection::parseReturnType($this->class->getMethod($name), true);
                if (is_array($type)) {
                    $type = $type[0] ?? null;
                }
                return $this->fakeValue($type?->getType());
            }
        } catch (\Throwable $e) {
        }

        return new self;
    }

    /**
     * @param string       $name
     * @param array<mixed> $args
     *
     * @return self
     */
    public static function __callStatic(string $name, array $args): self
    {
        return new self;
    }

    public function __invoke(): self
    {
        try {
            if ($this->class) {
                $type = Reflection::parseReturnType($this->class->getMethod('__invoke'), true);
                if (is_array($type)) {
                    $type = $type[0] ?? null;
                }
                return $this->fakeValue($type?->getType());
            }
        } catch (\Throwable $e) {
        }

        return new self;
    }

    public function __toString()
    {
        return '';
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->__set($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        //
    }

    public function toArray()
    {
        return [];
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * @return null
     */
    public function jsonSerialize(): mixed
    {
        return null;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator([]);
    }

    public function count(): int
    {
        return 0;
    }

    private function fakeValue(?string $type): mixed
    {
        if (!$type) {
            return new self;
        }

        if (Reflection::isBuiltin($type)) {
            return match ($type) {
                'bool' => false,
                'int' => 0,
                'float' => 0.0,
                'string' => '',
                'iterable', 'array' => [],
                'object' => (object)[],
                // 'mixed', 'null' => null,
                default => null,
            };
        }

        return Reflection::reflection($type)->newInstanceWithoutConstructor();
    }
}

