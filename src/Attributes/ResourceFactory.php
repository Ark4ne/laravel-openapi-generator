<?php

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class ResourceFactory
{
    /**
     * @param string $factory
     * @param array<mixed> $parameters
     * @param string|null $method
     */
    public function __construct(
        public string  $factory,
        public array   $parameters = [],
        public ?string $method = null
    )
    {
    }
}
