<?php

namespace Ark4ne\OpenApi\Parsers\Responses;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\Parser;

class JsonResponseParser implements Parser
{
    /**
     * @param \Illuminate\Http\JsonResponse   $element
     * @param \Ark4ne\OpenApi\Contracts\Entry $entry
     *
     * @return mixed
     */
    public function parse(mixed $element, Entry $entry): mixed
    {
        // TODO: Implement parse() method.
        return $element;
    }
}
