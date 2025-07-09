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

class JsonApiResourceParser implements ResponseParserContract
{
    use JAResource;
    use JAResourceRef;

    public function parse(Type $type, Entry $entry): ResponseEntry
    {
        $class = Reflection::reflection($type->getType());

        $body = $this->generateBody($class);
        $response = $this->generateResponse($body, $class);

        return $this->toResponseEntry($response, $entry, $body);
    }

    private function createResourceInstance(\ReflectionClass $class)
    {
        $instance = $class->newInstanceWithoutConstructor();
        $instance->resource = $this->getModelFromResource($class);

        return $instance;
    }

    private function generateBody(\ReflectionClass $class): ?Parameter
    {
        if (!Config::useRef()) {
            return null;
        }

        try {
            $properties = [
                (new Parameter('data'))->ref($this->resourceToRef($class->getName()))
            ];

            $includedRefs = ArrayCache::get(['ja-resource-ref', $class->getName()]);
            if (!empty($includedRefs)) {
                $properties[] = $this->createIncludedParameter($includedRefs);
            }

            return (new Parameter('body'))
                ->object()
                ->properties(...$properties);

        } catch (\Throwable $e) {
            Logger::error('Failed to generate body for JSON API resource response: ' . $e->getMessage());
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

    private function generateResponse(?Parameter $body, $class)
    {
        try {
            $instance = $this->createResourceInstance($class);
            $response = $instance->response();

            if (!$body) {
                $structure = $this->generateStructure($instance);
                $response = $this->mergeResponseWithStructure($response, $structure);
            }

            return $response;

        } catch (\Throwable $e) {
            if (!$body) {
                Logger::warn([
                    "Failed to generate response for resource - (class: " . $instance::class . ")",
                    $e->getMessage()
                ]);
                Logger::notice('Use fallback structure instead.');

                $structure = $this->generateStructure($instance);
                return response()->json($structure);
            }

            return response()->json();
        }
    }
}