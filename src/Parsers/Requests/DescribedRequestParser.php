<?php

namespace Ark4ne\OpenApi\Parsers\Requests;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\Parser;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Documentation\RequestEntry;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\RegexParser;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\RulesParser;

class DescribedRequestParser implements Parser
{
    use RegexParser, RulesParser;

    /**
     * @param class-string<\Ark4ne\OpenApi\Contracts\Documentation\DescribableRequest>|\Ark4ne\OpenApi\Contracts\Documentation\DescribableRequest $element
     * @param \Ark4ne\OpenApi\Contracts\Entry                                                                                                     $entry
     *
     * @return RequestEntry
     */
    public function parse(mixed $element, Entry $entry): RequestEntry
    {
        $element = is_string($element) ? new $element : $element;

        $describer = $element->describer();

        return new RequestEntry(
            securities: $describer->getSecurities(),
            parameters: collect($entry->getPathParameters())
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
