<?php

namespace Ark4ne\OpenApi\Parsers\Responses;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\ResponseParserContract;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Documentation\ResponseEntry;
use GoldSpecDigital\ObjectOrientedOAS\Objects\{
    MediaType,
};

class JsonResponseParser implements ResponseParserContract
{
    /**
     * @param \Illuminate\Http\JsonResponse   $element
     * @param \Ark4ne\OpenApi\Contracts\Entry $entry
     *
     * @return \Ark4ne\OpenApi\Documentation\ResponseEntry
     */
    public function parse(mixed $element, Entry $entry): ResponseEntry
    {
        return new ResponseEntry(
            format: MediaType::MEDIA_TYPE_APPLICATION_JSON,
            body: (new Parameter(''))->object()
        );
    }
}
