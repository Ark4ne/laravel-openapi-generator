<?php

namespace Ark4ne\OpenApi\Parsers\Requests;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\Parser;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\RulesParser;

class RequestParser implements Parser
{
    use RulesParser;

    /**
     * @param class-string<\Illuminate\Http\Request>|\Illuminate\Http\Request $element
     * @param \Ark4ne\OpenApi\Contracts\Entry                                 $entry
     *
     * @return mixed
     */
    public function parse(mixed $element, Entry $entry): mixed
    {
        $element = is_string($element) ? new $element : $element;

        return [
            'body' => $this->rules($element->rules())
        ];
    }
}
