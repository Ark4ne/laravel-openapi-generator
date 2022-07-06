<?php

namespace Ark4ne\OpenApi\Parsers\Responses;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\ResponseParserContract;
use Ark4ne\OpenApi\Documentation\ResponseEntry;
use Ark4ne\OpenApi\Errors\Log;
use Ark4ne\OpenApi\Parsers\Responses\Concerns\JAResource;
use Ark4ne\OpenApi\Support\Reflection;
use Ark4ne\OpenApi\Support\Reflection\Type;

class JsonApiCollectionParser implements ResponseParserContract
{
    use JAResource;

    public function parse(Type $type, Entry $entry): ResponseEntry
    {
        $class = Reflection::reflection($type->getType());

        $instance = $class->newInstanceWithoutConstructor();

        if (!($resource = $this->getResourceFromCollection($instance, $type))) {
            Log::warn('Response', "Can't determinate resource type for collection : " . $instance::class);
        }

        $instance->collects = $resource;

        $resourceClass = Reflection::reflection($resource);
        $resourceInstance = $resourceClass->newInstanceWithoutConstructor();
        $resourceInstance->resource = $this->getModelFromResource($resourceClass);

        $structure = $this->generateStructure($resourceInstance, $class);

        try {
            $instance->collection = collect($this->getModelFromResource($resourceClass, 2))
                ->mapInto($resource);

            /** @var \Illuminate\Http\JsonResponse $response */
            $response = $instance->response();

            $response = $this->mergeResponseWithStructure($response, $structure);
        } catch (\Throwable $e) {
            $response = response()->json($structure);
        }

        return $this->toResponseEntry($response);
    }

}
