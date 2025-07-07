<?php

namespace Ark4ne\OpenApi\Parsers\Responses;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\ResponseParserContract;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Documentation\ResponseEntry;
use Ark4ne\OpenApi\Parsers\Responses\Concerns\JAResource;
use Ark4ne\OpenApi\Parsers\Responses\Concerns\JAResourceRef;
use Ark4ne\OpenApi\Support\ArrayCache;
use Ark4ne\OpenApi\Support\Config;
use Ark4ne\OpenApi\Support\Facades\Logger;
use Ark4ne\OpenApi\Support\Reflection;
use Ark4ne\OpenApi\Support\Reflection\Type;
use GoldSpecDigital\ObjectOrientedOAS\Objects\AnyOf;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;
use Illuminate\Pagination\LengthAwarePaginator;

class JsonApiCollectionParser implements ResponseParserContract
{
    use JAResource;
    use JAResourceRef;

    public function parse(Type $type, Entry $entry): ResponseEntry
    {
        $class = Reflection::reflection($type->getType());
        $instance = $class->newInstanceWithoutConstructor();
        $resource = $this->getResourceFromCollection($instance, $type);

        if (!$resource) {
            Logger::warn("Can't determinate resource type for collection : " . $instance::class);
            return $this->createFallbackResponse($entry);
        }

        $instance->collects = $resource;
        $resourceClass = Reflection::reflection($resource);
        $resourceInstance = $resourceClass->newInstanceWithoutConstructor();
        $resourceInstance->resource = $this->getModelFromResource($resourceClass);

        $body = $this->generateBody($resource, $resourceClass);
        $collection = $this->createCollection($resourceClass, $resource, $entry);

        $instance->resource = $collection;
        $instance->collection = $collection;

        $response = $this->generateResponse($instance, $body, $resourceInstance, $resourceClass);

        return $this->toResponseEntry($response, $entry, $body);
    }

    private function generateBody($resource, $resourceClass): ?Parameter
    {
        if (!Config::useRef()) {
            return null;
        }

        try {
            $properties = [
                (new Parameter('data'))
                    ->array()
                    ->items((new Parameter(''))->ref($this->resourceToRef($resource)))
            ];

            $includedRefs = ArrayCache::get(['ja-resource-ref', $resourceClass->getName()]);
            if (!empty($includedRefs)) {
                $properties[] = (new Parameter('included'))
                    ->array()
                    ->items(
                        (new AnyOf())->schemas(
                            ...array_values(array_map(
                                fn($ref) => Schema::ref($ref),
                                $includedRefs
                            ))
                        )
                    );
            }

            return (new Parameter('body'))
                ->object()
                ->properties(...$properties);

        } catch (\Throwable $e) {
            Logger::error('Failed to generate body for JSON API collection response: ' . $e->getMessage());
            return null;
        }
    }

    private function createCollection($resourceClass, $resource, Entry $entry)
    {
        $collection = collect($this->getModelFromResource($resourceClass, 2))->mapInto($resource);

        if ($entry->getDocResponsePaginate()) {
            $collection = new LengthAwarePaginator($collection, $collection->count(), 15);
        }

        return $collection;
    }

    private function generateResponse($instance, $body, $resourceInstance, $resourceClass)
    {
        try {
            $response = $instance->response();

            if (!$body) {
                $structure = $this->generateStructure($resourceInstance, Reflection::reflection($instance::class));
                $response = $this->mergeResponseWithStructure($response, $structure);
            }

            return $response;

        } catch (\Throwable $e) {
            if (!$body) {
                Logger::warn([
                    "Fail to generate response for collection - (collect class : " . $instance::class . ")",
                    $e->getMessage()
                ]);
                Logger::notice('Use resource structure instead.');

                $structure = $this->generateStructure($resourceInstance, Reflection::reflection($instance::class));
                return response()->json(['data' => [$structure['data'], $structure['data']]]);
            }

            return response()->json();
        }
    }

    private function createFallbackResponse(Entry $entry): ResponseEntry
    {
        $response = response()->json();
        return $this->toResponseEntry($response, $entry, null);
    }
}
