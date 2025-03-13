<?php

namespace Ark4ne\OpenApi\Parsers\Responses;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\ResponseParserContract;
use Ark4ne\OpenApi\Documentation\ResponseEntry;
use Ark4ne\OpenApi\Parsers\Responses\Concerns\JAResource;
use Ark4ne\OpenApi\Support\Facades\Logger;
use Ark4ne\OpenApi\Support\Reflection;
use Ark4ne\OpenApi\Support\Reflection\Type;
use Illuminate\Pagination\LengthAwarePaginator;

class JsonApiCollectionParser implements ResponseParserContract
{
    use JAResource;

    public function parse(Type $type, Entry $entry): ResponseEntry
    {
        $class = Reflection::reflection($type->getType());

        $instance = $class->newInstanceWithoutConstructor();

        if (!($resource = $this->getResourceFromCollection($instance, $type))) {
            Logger::warn("Can't determinate resource type for collection : " . $instance::class);
        }

        $instance->collects = $resource;

        $resourceClass = Reflection::reflection($resource);
        $resourceInstance = $resourceClass->newInstanceWithoutConstructor();
        $resourceInstance->resource = $this->getModelFromResource($resourceClass);

        $structure = $this->generateStructure($resourceInstance, $class);

        try {
            $collection = collect($this->getModelFromResource($resourceClass, 2))->mapInto($resource);

            if ($entry->getDocResponsePaginate()) {
                $collection = new LengthAwarePaginator($collection, $collection->count(), 15);
            }

            $instance->resource = $collection;
            $instance->collection = $collection;

            /** @var \Illuminate\Http\JsonResponse $response */
            $response = $instance->response();

            $response = $this->mergeResponseWithStructure($response, $structure);
        } catch (\Throwable $e) {
            Logger::warn([
                "Fail to generate response for collection of {{$resource}} - (collect class : {{$class->getName()}})",
                $e->getMessage()
            ]);
            Logger::notice('Use resource structure instead.');
            $response = response()->json(['data' => [$structure['data'], $structure['data']]]);
        }

        return $this->toResponseEntry($response, $entry);
    }

}
