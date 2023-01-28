<?php

namespace Ark4ne\OpenApi\Documentation\Request\Concerns;

use GoldSpecDigital\ObjectOrientedOAS\Objects\BaseObject;

/**
 * @template T as BaseObject
 * @mixin T
 */
trait OASProxy
{
    /**
     * @var T
     */
    protected $object;

    /**
     * @param string       $name
     * @param array<mixed> $args
     *
     * @return mixed
     */
    public function __call(string $name, array $args): mixed
    {
        $this->object = $this->object->$name(...$args);

        return $this;
    }

    public function __get(string $name): mixed
    {
        return $this->object->$name;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->object->$name = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->object->$name);
    }

    /**
     * @return T
     */
    public function oasSchema(): mixed
    {
        return $this->object;
    }
}
