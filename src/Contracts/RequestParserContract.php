<?php

namespace Ark4ne\OpenApi\Contracts;

use Ark4ne\OpenApi\Documentation\RequestEntry;

interface RequestParserContract
{
    public function parse(mixed $element, Entry $entry): RequestEntry;
}
