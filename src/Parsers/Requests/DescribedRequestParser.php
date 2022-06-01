<?php

namespace Ark4ne\OpenApi\Parsers\Requests;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\Parser;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\RulesParser;

class DescribedRequestParser implements Parser
{
    use RulesParser;

    /**
     * @param class-string<\Ark4ne\OpenApi\Contracts\Documentation\DescribableRequest>|\Ark4ne\OpenApi\Contracts\Documentation\DescribableRequest $element
     * @param \Ark4ne\OpenApi\Contracts\Entry                                                                                                     $entry
     *
     * @return array{headers: array<string, \Ark4ne\OpenApi\Documentation\Request\Body\Parameter>, body: array<string, \Ark4ne\OpenApi\Documentation\Request\Body\Parameter>, queries: array<string, \Ark4ne\OpenApi\Documentation\Request\Body\Parameter>}
     */
    public function parse(mixed $element, Entry $entry): array
    {
        $element = is_string($element) ? new $element : $element;

        $describer = $element->describer();

        return [
            'headers' => $describer->getHeaders(),
            'body' => $this->rules($describer->getBody())->all(),
            'queries' => $this->rules($describer->getQueries())->all(),
        ];
    }
}
