<?php

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Id
{
    public function __construct(public string $id)
    {
    }
}
