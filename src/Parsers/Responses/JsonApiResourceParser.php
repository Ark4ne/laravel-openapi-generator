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

        $instance = $class->newInstanceWithoutConstructor();
        $instance->resource = $this->getModelFromResource($class);

        try {
            $properties[] = (new Parameter('data'))->ref($this->resourceToRef($instance));

            if (!empty(ArrayCache::get(['ja-resource-ref', $instance::class]))) {
                $properties[] = (new Parameter('included'))
                    ->array()
                    ->items(
                        (new AnyOf())
                            ->schemas(
                                ...array_values(array_map(
                                    fn($ref) => Schema::ref($ref),
                                    ArrayCache::get(['ja-resource-ref', $instance::class]),
                                ))
                            )
                    );
            }

            $body = (new Parameter('body'))
                ->object()
                ->properties(...$properties);
        } catch (\Throwable $e) {
            $body = null;

            Logger::error('Failed to generate body for JSON API collection response: ' . $e->getMessage());
        }

        if (!$body) {
            $structure = $this->generateStructure($instance, $class);

            try {
                /** @var \Illuminate\Http\JsonResponse $response */
                $response = $instance->response();
                $response = $this->mergeResponseWithStructure($response, $structure);
            } catch (\Throwable $e) {
                $response = response()->json($structure);
            }
        } else {
            try {
                /** @var \Illuminate\Http\JsonResponse $response */
                $response = $instance->response();
            } catch (\Throwable $e) {
                $response = response()->json();
            }
        }

        return $this->toResponseEntry($response, $entry, $body);
    }

}
