<?php

namespace Ark4ne\OpenApi\Parsers\Responses;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\ResponseParserContract;
use Ark4ne\OpenApi\Documentation\ResponseEntry;
use GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType;

class StreamedResponseParser implements ResponseParserContract
{
    public function parse(mixed $element, Entry $entry): ResponseEntry
    {
        return new ResponseEntry('application/octet-stream', body: MediaType::create()->mediaType(MediaType::MEDIA_TYPE_TEXT_PLAIN));
    }
}
