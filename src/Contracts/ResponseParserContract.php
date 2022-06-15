<?php

namespace Ark4ne\OpenApi\Contracts;

use Ark4ne\OpenApi\Documentation\ResponseEntry;
use Ark4ne\OpenApi\Support\Reflection\Type;

interface ResponseParserContract
{
    /**
     * @template T of \Illuminate\Http\Response
     * @template U of null|class-string
     *
     * @param \Ark4ne\OpenApi\Support\Reflection\Type<T, U> $element
     * @param \Ark4ne\OpenApi\Contracts\Entry $entry
     *
     * @return \Ark4ne\OpenApi\Documentation\ResponseEntry
     */
    public function parse(Type $element, Entry $entry): ResponseEntry;
}
