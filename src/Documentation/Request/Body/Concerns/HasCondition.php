<?php

namespace Ark4ne\OpenApi\Documentation\Request\Body\Concerns;

use Ark4ne\OpenApi\Documentation\Request\Body\Condition;

trait HasCondition
{
    /** @var array<Condition> */
    protected array $conditions = [];

    public function with(string|array $attrs, null|string $rule = null): static
    {
        $this->conditions[] = new Condition(Condition::TYPE_WITH, null, $attrs, $rule);
        return $this;
    }

    public function without(string|array $attrs, null|string $rule = null): static
    {
        $this->conditions[] = new Condition(Condition::TYPE_WITHOUT, null, $attrs, $rule);
        return $this;
    }

    public function withAll(array|string $attrs, null|string $rule = null): static
    {
        $this->conditions[] = new Condition(Condition::TYPE_WITH_ALL, null, $attrs, $rule);
        return $this;
    }

    public function withoutAll(array|string $attrs, null|string $rule = null): static
    {
        $this->conditions[] = new Condition(Condition::TYPE_WITHOUT_ALL, null, $attrs, $rule);
        return $this;
    }

    public function if(string $attribute, array|string $value, null|string $rule = null): static
    {
        $this->conditions[] = new Condition(Condition::TYPE_IF, $attribute, $value, $rule);
        return $this;
    }

    public function unless(string $attribute, array|string $value, null|string $rule = null): static
    {
        $this->conditions[] = new Condition(Condition::TYPE_UNLESS, $attribute, $value, $rule);
        return $this;
    }

    public function greater(string $attribute): static
    {
        $this->conditions[] = new Condition("greater than", $attribute);
        return $this;
    }

    public function greaterOrEquals(string $attribute): static
    {
        $this->conditions[] = new Condition("greater or equals than", $attribute);
        return $this;
    }

    public function less(string $attribute): static
    {
        $this->conditions[] = new Condition("less than", $attribute);
        return $this;
    }

    public function lessOrEquals(string $attribute): static
    {
        $this->conditions[] = new Condition("less or equals than", $attribute);
        return $this;
    }

    public function different(string $attribute): static
    {
        $this->conditions[] = new Condition("different than", $attribute);
        return $this;
    }

    public function same(string $attribute): static
    {
        $this->conditions[] = new Condition("same than", $attribute);
        return $this;
    }
}
