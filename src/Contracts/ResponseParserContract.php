<?php

namespace Ark4ne\OpenApi\Contracts;

use Ark4ne\OpenApi\Documentation\ResponseEntry;

interface ResponseParserContract
{
    public function parse(mixed $element, Entry $entry): ResponseEntry;
}
