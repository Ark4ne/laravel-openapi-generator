<?php

namespace Ark4ne\OpenApi\Parsers\Responses;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\ResponseParserContract;
use Ark4ne\OpenApi\Documentation\ResponseEntry;

class ResponseParser implements ResponseParserContract
{

    public function parse(mixed $element, Entry $entry): ResponseEntry
    {
        // TODO: Implement parse() method.
        return new ResponseEntry('unknown');
    }
}
