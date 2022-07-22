<?php

namespace Ark4ne\OpenApi\Parsers\Requests;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\RequestParserContract;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Documentation\RequestEntry;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\RegexParser;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\RulesParser;
use Ark4ne\OpenApi\Support\Reflection\Type;

class DescribedRequestParser implements RequestParserContract
{
    use RegexParser, RulesParser;

    /**
     * @param Type<\Ark4ne\OpenApi\Contracts\Documentation\DescribableRequest, null> $element
     * @param \Ark4ne\OpenApi\Contracts\Entry                                                      $entry
     *
     * @return RequestEntry
     */
    public function parse(Type $element, Entry $entry): RequestEntry
    {
        $element = new ($element->getType());

        $describer = $element->describer();

        return new RequestEntry(
            securities: $describer->getSecurities(),
            parameters: collect($entry->getRouteParams())
                ->map(fn(?string $pattern, string $name) => tap(new Parameter($name), fn($param) => $pattern
                    ? $this->parseRegex($param, $pattern)
                    : null))
                ->all(),
            headers: $describer->getHeaders(),
            body: $this->rules($describer->getBody())->all(),
            queries: $this->rules($describer->getQueries())->all(),
        );
    }
}
