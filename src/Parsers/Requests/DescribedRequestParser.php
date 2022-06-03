<?php

namespace Ark4ne\OpenApi\Parsers\Requests;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\Parser;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\RegexParser;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\RulesParser;

class DescribedRequestParser implements Parser
{
    use RegexParser, RulesParser;

    /**
     * @param class-string<\Ark4ne\OpenApi\Contracts\Documentation\DescribableRequest>|\Ark4ne\OpenApi\Contracts\Documentation\DescribableRequest $element
     * @param \Ark4ne\OpenApi\Contracts\Entry                                                                                                     $entry
     *
     * @return array{
     *     parameters: array<string, \Ark4ne\OpenApi\Documentation\Request\Parameter>,
     *     headers: array<string, \Ark4ne\OpenApi\Documentation\Request\Parameter>,
     *     body: array<string, \Ark4ne\OpenApi\Documentation\Request\Parameter>,
     *     queries: array<string, \Ark4ne\OpenApi\Documentation\Request\Parameter>
     * }
     */
    public function parse(mixed $element, Entry $entry): array
    {
        $element = is_string($element) ? new $element : $element;

        $describer = $element->describer();

        return [
            'parameters' => collect($entry->getPathParameters())
                ->map(fn(?string $pattern, string $name) => tap(new Parameter($name), fn($param) => $pattern
                    ? $this->parseRegex($param, $pattern)
                    : null))
                ->all(),
            'headers' => $describer->getHeaders(),
            'body' => $this->rules($describer->getBody())->all(),
            'queries' => $this->rules($describer->getQueries())->all(),
        ];
    }
}
