<?php

namespace Ark4ne\OpenApi\Parsers\Responses;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\ResponseParserContract;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Documentation\ResponseEntry;
use Ark4ne\OpenApi\Parsers\Responses\Concerns\Response;
use GoldSpecDigital\ObjectOrientedOAS\Objects\{
    MediaType,
};

class JsonResponseParser implements ResponseParserContract
{
    use Response;

    /**
     * @param \Illuminate\Http\JsonResponse   $element
     * @param \Ark4ne\OpenApi\Contracts\Entry $entry
     *
     * @return \Ark4ne\OpenApi\Documentation\ResponseEntry
     */
    public function parse(mixed $element, Entry $entry): ResponseEntry
    {
        return new ResponseEntry(
            $this->getContentType($entry),
            statusCode: $entry->getDocResponseStatusCode() ?? 0,
            statusName: $entry->getDocResponseStatusName() ?? '',
            headers: $this->convertHeadersToOasHeaders($this->getHeaders($entry)),
            body: (new Parameter(''))->object()
        );
    }

    protected function defaultContentType(): string
    {
        return MediaType::MEDIA_TYPE_APPLICATION_JSON;
    }
}
