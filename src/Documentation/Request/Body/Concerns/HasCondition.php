<?php

namespace Ark4ne\OpenApi\Documentation\Request\Body\Concerns;

use Ark4ne\OpenApi\Documentation\Request\Body\Condition;

trait HasCondition
{
    protected Condition $condition;

    public function if(string $attribute, array|string $value): static
    {
        $this->condition = new Condition(Condition::TYPE_IF, $attribute, $value);
        return $this;
    }

    public function unless(string $attribute, array|string $value): static
    {
        $this->condition = new Condition(Condition::TYPE_UNLESS, $attribute, $value);
        return $this;
    }
}
