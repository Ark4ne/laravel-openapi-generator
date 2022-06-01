<?php

namespace Ark4ne\OpenApi\Documentation\Request\Body;

class Condition
{
    public const TYPE_IF = 'if';
    public const TYPE_UNLESS = 'unless';

    /**
     * @param string       $type
     * @param string       $attrs
     * @param array<mixed>|string $value
     */
    public function __construct(
        protected string $type,
        protected string $attrs,
        protected array|string $value,
    ) {
    }
}
