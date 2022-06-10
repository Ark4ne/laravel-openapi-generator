<?php

namespace Ark4ne\OpenApi\Parsers\Requests;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\RequestParserContract;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Documentation\RequestEntry;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\RegexParser;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\RulesParser;

class RequestParser implements RequestParserContract
{
    use RegexParser, RulesParser;

    /**
     * @param class-string<\Illuminate\Http\Request>|\Illuminate\Http\Request $element
     * @param \Ark4ne\OpenApi\Contracts\Entry                                 $entry
     *
     * @return RequestEntry
     */
    public function parse(mixed $element, Entry $entry): RequestEntry
    {
        $element = is_string($element) ? new $element : $element;

        $parameters = collect($entry->getPathParameters())
            ->map(function (?string $pattern, string $name) {
                $param = new Parameter($name);
                if ($pattern) {
                    $this->parseRegex($param, $pattern);
                }
                return $param;
            })
            ->all();

        $body = [];

        if (method_exists($element, 'rules')) {
            $body = $this->rules($element->rules())->all();
        }

        return new RequestEntry(
            parameters: $parameters,
            body:$body
        );
    }
}
