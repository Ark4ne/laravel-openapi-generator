<?php

namespace Ark4ne\OpenApi\Documentation\Request;

use GoldSpecDigital\ObjectOrientedOAS\Objects\Components;

class Component
{
    private static array $refs = [];

    protected Parameter $object;

    private function __construct(
        protected string $id
    ) {
    }

    public static function create(string $id): self
    {
        if (isset(self::$refs[$id])) {
            throw new \InvalidArgumentException('Already exists');
        }

        return self::$refs[$id] = new self($id);
    }

    public static function has(string $id): bool
    {
        return isset(self::$refs[$id]);
    }


    public static function get(string $id): ?self
    {
        return self::$refs[$id] ?? null;
    }

    public function ref(): string
    {
        return "#/components/schemas/$this->id";
    }

    public function object(Parameter $parameter): self
    {
        $this->object = $parameter;

        return $this;
    }

    public static function convert(): ?Components
    {
        if (empty(self::$refs)) {
            return null;
        }

        return Components::create()->schemas(...array_map(
            static fn(self $component) => $component->object->oasSchema(),
            array_values(self::$refs)
        ));
    }
}
