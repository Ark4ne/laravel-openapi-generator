<?php

namespace Ark4ne\OpenApi\Attributes;


use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Request
{
    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(
        public array       $headers = [],
    )
    {
    }
}