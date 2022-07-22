<?php

namespace Ark4ne\OpenApi\Parsers\Requests;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\RequestParserContract;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Documentation\RequestEntry;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\RegexParser;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\RulesParser;
use Ark4ne\OpenApi\Support\Reflection\Type;

class RequestParser implements RequestParserContract
{
    use RegexParser, RulesParser;

    /**
     * @param \Ark4ne\OpenApi\Support\Reflection\Type<\Illuminate\Http\Request, null> $element
     * @param \Ark4ne\OpenApi\Contracts\Entry                                         $entry
     *
     * @return RequestEntry
     */
    public function parse(Type $element, Entry $entry): RequestEntry
    {
        $element = new ($element->getType());

        $parameters = collect($entry->getRouteParams())
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
            $body = $this->rules($entry, $element->rules())->all();
        }

        return new RequestEntry(
            parameters: $parameters,
            body: $body
        );
    }
}
