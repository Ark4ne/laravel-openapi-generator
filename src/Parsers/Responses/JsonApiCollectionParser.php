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
            Logger::warn("Can't determine resource type for collection: " . $instance::class);
            return $this->createFallbackResponse($entry);
        }

        $resourceClass = Reflection::reflection($resource);

        $body = $this->generateBody($resourceClass);
        $collection = $this->createCollection($resourceClass, $entry);

        $instance->collects = $resource;
        $instance->resource = $collection;
        $instance->collection = $collection;

        $response = $this->generateResponse($instance, $body, $resourceClass, $collection->first());

        return $this->toResponseEntry($response, $entry, $body);
    }

    private function createResourceInstance(\ReflectionClass $class)
    {
        $instance = $class->newInstanceWithoutConstructor();
        $instance->resource = $this->getModelFromResource($class);

        return $instance;
    }

    private function generateBody($resourceClass): ?Parameter
    {
        if (!Config::useRef()) {
            return null;
        }

        try {
            $properties = [
                (new Parameter('data'))
                    ->array()
                    ->items((new Parameter(''))->ref($this->resourceToRef($resourceClass->getName())))
            ];

            $includedRefs = ArrayCache::get(['ja-resource-ref', $resourceClass->getName()]);
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

    private function createCollection(\ReflectionClass $resourceClass, Entry $entry)
    {
        $collection = collect($this->getModelFromResource($resourceClass, 2))->mapInto($resourceClass->getName());

        if ($entry->getDocResponsePaginate()) {
            $collection = new LengthAwarePaginator($collection, $collection->count(), 15);
        }

        return $collection;
    }

    private function generateResponse($instance, $body, $resourceClass, $resourceInstance)
    {
        try {
            $response = $instance->response();

            if (!$body) {
                $structure = $this->generateStructure($resourceInstance);
                $response = $this->mergeResponseWithStructure($response, $structure);
            }

            return $response;
        } catch (\Throwable $e) {
            if (!$body) {
                Logger::warn([
                    "Failed to generate response for collection - (collect class: " . $instance::class . ", resource class: " . $resourceClass->getName() . ")",
                    $e->getMessage()
                ]);
                Logger::notice('Use resource structure instead.');

                $structure = $this->generateStructure($resourceInstance);
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