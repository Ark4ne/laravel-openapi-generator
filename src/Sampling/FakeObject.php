<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Types\TypeResolver;
use JsonSerializable;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Stands for an instance of a class that cannot be built: each property and each method answers a value of its
 * declared type (property, `@property`, return type), `null` when nothing is declared.
 */
final class FakeObject implements JsonSerializable
{
    /**
     * @param class-string $class
     */
    public function __construct(
        private readonly string $class,
        private readonly FakeValues $values,
        private readonly TypeResolver $types,
        private readonly int $depth = 1,
    ) {}

    /**
     * @return class-string
     */
    public function class(): string
    {
        return $this->class;
    }

    public function __get(string $name): mixed
    {
        if (property_exists($this->class, $name)) {
            $type = $this->types->propertyType(new ReflectionProperty($this->class, $name));

            return $type === null ? null : $this->values->for($type, $this->depth);
        }

        $type = $this->types->properties(new ReflectionClass($this->class))[$name] ?? null;

        return $type === null ? null : $this->values->for($type, $this->depth);
    }

    public function __isset(string $name): bool
    {
        return property_exists($this->class, $name) || isset($this->types->properties(new ReflectionClass($this->class))[$name]);
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (! method_exists($this->class, $name)) {
            return null;
        }

        $type = $this->types->returnType(new ReflectionMethod($this->class, $name));

        return $type === null ? null : $this->values->for($type, $this->depth);
    }

    /**
     * @return array<string, mixed> the declared properties and their values
     */
    public function jsonSerialize(): array
    {
        $values = [];

        foreach ((new ReflectionClass($this->class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $values[$property->getName()] = $this->__get($property->getName());
        }

        foreach (array_keys($this->types->properties(new ReflectionClass($this->class))) as $name) {
            $values[$name] = $this->__get($name);
        }

        return $values;
    }
}
