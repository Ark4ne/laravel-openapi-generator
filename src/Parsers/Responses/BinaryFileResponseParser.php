<?php

namespace Ark4ne\OpenApi\Parsers\Responses;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\ResponseParserContract;
use Ark4ne\OpenApi\Documentation\ResponseEntry;
use Ark4ne\OpenApi\Parsers\Responses\Concerns\Response;
use GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType;

class BinaryFileResponseParser implements ResponseParserContract
{
    use Response;

    public function parse(mixed $element, Entry $entry): ResponseEntry
    {
        return new ResponseEntry(
            $this->getContentType($entry),
            statusCode: $entry->getDocResponseStatusCode() ?? 0,
            statusName: $entry->getDocResponseStatusName() ?? '',
            headers: $this->convertHeadersToOasHeaders($this->getHeaders($entry)),
            body: MediaType::create()->mediaType($this->getMediaType($entry))
        );
    }

    protected function defaultContentType(): string
    {
        return 'application/octet-stream';
    }
}
