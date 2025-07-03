<?php

namespace Ark4ne\OpenApi\Parsers\Responses;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\ResponseParserContract;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Documentation\ResponseEntry;
use Ark4ne\OpenApi\Parsers\Responses\Concerns\JAResource;
use Ark4ne\OpenApi\Parsers\Responses\Concerns\JAResourceRef;
use Ark4ne\OpenApi\Support\ArrayCache;
use Ark4ne\OpenApi\Support\Facades\Logger;
use Ark4ne\OpenApi\Support\Reflection;
use Ark4ne\OpenApi\Support\Reflection\Type;
use GoldSpecDigital\ObjectOrientedOAS\Objects\AnyOf;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;

class JsonApiResourceParser implements ResponseParserContract
{
    use JAResource;
    use JAResourceRef;

    public function parse(Type $type, Entry $entry): ResponseEntry
    {
        $class = Reflection::reflection($type->getType());
        $instance = $this->createResourceInstance($class);

        $body = $this->generateBody($instance);
        $response = $this->generateResponse($instance, $body, $class);

        return $this->toResponseEntry($response, $entry, $body);
    }

    private function createResourceInstance($class)
    {
        $instance = $class->newInstanceWithoutConstructor();
        $instance->resource = $this->getModelFromResource($class);

        return $instance;
    }

    private function generateBody($instance): ?Parameter
    {
        try {
            $properties = [(new Parameter('data'))->ref($this->resourceToRef($instance))];

            $includedRefs = ArrayCache::get(['ja-resource-ref', $instance::class]);
            if (!empty($includedRefs)) {
                $properties[] = $this->createIncludedParameter($includedRefs);
            }

            return (new Parameter('body'))
                ->object()
                ->properties(...$properties);

        } catch (\Throwable $e) {
            Logger::error('Failed to generate body for JSON API collection response: ' . $e->getMessage());
            return null;
        }
    }

    private function createIncludedParameter(array $refs): Parameter
    {
        return (new Parameter('included'))
            ->array()
            ->items(
                (new AnyOf())->schemas(
                    ...array_values(array_map(
                        fn($ref) => Schema::ref($ref),
                        $refs
                    ))
                )
            );
    }

    private function generateResponse($instance, ?Parameter $body, $class)
    {
        try {
            $response = $instance->response();

            if (!$body) {
                $structure = $this->generateStructure($instance, $class);
                $response = $this->mergeResponseWithStructure($response, $structure);
            }

            return $response;

        } catch (\Throwable $e) {
            if (!$body) {
                return response()->json($this->generateStructure($instance, $class));
            }

            return response()->json();
        }
    }
}
