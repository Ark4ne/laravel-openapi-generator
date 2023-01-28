<?php

namespace Ark4ne\OpenApi\Documentation;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Support\Facades\Logger;
use Ark4ne\OpenApi\Support\Reflection\Type;

class ComputeEntry
{
    public function __construct(protected Entry $entry)
    {
    }

    /**
     * @return array{RequestEntry, ResponseEntry[]}
     */
    public function __invoke(): array
    {
        $request = $this->request();
        $responses = $this->response();

        return [$request, $responses];
    }

    private function request(): RequestEntry
    {
        Logger::start('request ');
        $request = $this->parse(config('openapi.parsers.requests'), $this->entry->getRequestClass())[0] ?? null;
        Logger::end('success');

        $middlewares = array_intersect_key(
            config('openapi.parsers.middlewares', []),
            array_fill_keys($this->entry->getMiddlewares(), true)
        );

        if (!empty($middlewares)) {
            try {
                Logger::start('middlewares ');
                $parsers = array_unique(array_merge(...array_values(array_map(static fn($parsers) => (array)$parsers, $middlewares))));
                foreach ($parsers as $parser) {
                    app()->make($parser)->parse($this->entry, $request);
                }
                Logger::end('success');
            } catch (\Throwable $e) {
                Logger::end('error', 'Error when trying to parse middlewares : ' . $e->getMessage());
            }
        }

        return $request;
    }

    /**
     * @return ResponseEntry[]
     */
    private function response(): array
    {
        $responses = [];
        try {
            Logger::start('response ');
            $responses = $this->parse(config('openapi.parsers.responses'), $this->entry->getResponseClass());
            Logger::end('success');
        } catch (\Throwable $e) {
            Logger::end('error', 'Error when trying to generate response : ' . $e->getMessage());
        }
        return $responses;
    }

    /**
     * @param array<class-string> $parsers
     * @param Type[]|Type|null $element
     *
     * @return null|array
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    private function parse(array $parsers, null|array|Type $element): ?array
    {
        if ($element === null) {
            return null;
        }

        if (!is_array($element)) {
            $element = [$element];
        }

        $mapped = [];

        foreach ($element as $item) {
            foreach ($parsers as $for => $parser) {
                if (is_a($item->getType(), $for, true)) {
                    $mapped[] = app()->make($parser)->parse($item, $this->entry);
                    break;
                }
            }
        }

        if (!empty($mapped)) {
            return $mapped;
        }

        throw new \Exception("TODO: Can't parse " . (is_array($element)
                ? implode(', ', array_map(static fn($element) => $element->getType(), $element))
                : $element->getType()));
    }
}
