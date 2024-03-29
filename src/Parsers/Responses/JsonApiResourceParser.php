<?php

namespace Ark4ne\OpenApi\Parsers\Responses;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Contracts\ResponseParserContract;
use Ark4ne\OpenApi\Documentation\ResponseEntry;
use Ark4ne\OpenApi\Parsers\Responses\Concerns\JAResource;
use Ark4ne\OpenApi\Parsers\Responses\Concerns\JAResourceRef;
use Ark4ne\OpenApi\Support\Reflection;
use Ark4ne\OpenApi\Support\Reflection\Type;

class JsonApiResourceParser implements ResponseParserContract
{
    use JAResource;
    use JAResourceRef;

    public function parse(Type $type, Entry $entry): ResponseEntry
    {
        $class = Reflection::reflection($type->getType());

        $instance = $class->newInstanceWithoutConstructor();
        $instance->resource = $this->getModelFromResource($class);

        $structure = $this->generateStructure($instance, $class);

        try {
            /** @var \Illuminate\Http\JsonResponse $response */
            $response = $instance->response();
            $response = $this->mergeResponseWithStructure($response, $structure);
        } catch (\Throwable $e) {
            $response = response()->json($structure);
        }

        try {
            $body = null; // (new Parameter('body'))->ref($this->resourceToRef($instance));
        } catch (\Throwable $e) {
            $body = null;
        }

        return $this->toResponseEntry($response, $entry, $body);
    }

}
