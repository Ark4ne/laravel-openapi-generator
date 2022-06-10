<?php

namespace Ark4ne\OpenApi\Parsers\Responses;

use Ark4ne\OpenApi\Contracts\ResponseParserContract;
use Ark4ne\OpenApi\Parsers\Responses\Concerns\Resource;

class JsonResourceParser implements ResponseParserContract
{
    use Resource;
}
