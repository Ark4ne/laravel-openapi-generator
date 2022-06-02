<?php

namespace Ark4ne\OpenApi\Parsers\Requests;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\Parser;
use Ark4ne\OpenApi\Documentation\Request\Body\Parameter;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\RegexParser;
use Ark4ne\OpenApi\Parsers\Requests\Concerns\RulesParser;

class RequestParser implements Parser
{
    use RegexParser, RulesParser;

    /**
     * @param class-string<\Illuminate\Http\Request>|\Illuminate\Http\Request $element
     * @param \Ark4ne\OpenApi\Contracts\Entry                                 $entry
     *
     * @return  array{
     *     parameters: array<string, \Ark4ne\OpenApi\Documentation\Request\Body\Parameter>,
     *     body?: array<string, \Ark4ne\OpenApi\Documentation\Request\Body\Parameter>,
     * }
     */
    public function parse(mixed $element, Entry $entry): array
    {
        $element = is_string($element) ? new $element : $element;

        $parsed = [
            'parameters' => collect($entry->getPathParameters())
                ->map(fn(?string $pattern, string $name) => tap(new Parameter($name), fn($param) => $pattern
                    ? $this->parseRegex($param, $pattern)
                    : null))
                ->all(),
        ];

        if (method_exists($element, 'rules')) {
            $parsed['body'] = $this->rules($element->rules());
        }

        return $parsed;
    }
}
