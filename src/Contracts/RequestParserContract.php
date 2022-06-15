<?php

namespace Ark4ne\OpenApi\Contracts;

use Ark4ne\OpenApi\Documentation\RequestEntry;
use Ark4ne\OpenApi\Support\Reflection\Type;

interface RequestParserContract
{
    /**
     * @template T of \Illuminate\Http\Response
     *
     * @param \Ark4ne\OpenApi\Support\Reflection\Type<T, null> $element
     * @param \Ark4ne\OpenApi\Contracts\Entry            $entry
     *
     * @return \Ark4ne\OpenApi\Documentation\RequestEntry
     */
    public function parse(Type $element, Entry $entry): RequestEntry;
}
