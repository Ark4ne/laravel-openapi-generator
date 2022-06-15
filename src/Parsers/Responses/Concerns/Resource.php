<?php

namespace Ark4ne\OpenApi\Parsers\Responses\Concerns;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Documentation\ResponseEntry;
use Ark4ne\OpenApi\Support\Fake;
use Ark4ne\OpenApi\Support\Reflection;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Header;
use GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;

trait Resource
{
    /**
     * @param \Ark4ne\OpenApi\Support\Reflection\Type<JsonResource|ResourceCollection> $element
     * @param \Ark4ne\OpenApi\Contracts\Entry                                          $entry
     *
     * @return ResponseEntry
     */
    public function parse(Reflection\Type $element, Entry $entry): ResponseEntry
    {
        $status = 200;
        $headers = [];
        $parameter = null;

        if ($response = $this->getResponse($element, $entry)) {
            $status = $response->getStatusCode();
            $parameter = Parameter::fromJson($response->getData(true));
            foreach ($response->headers->allPreserveCase() as $name => $value) {
                $headers[] = Header::create($name)->example($value);
            }
        }

        return new ResponseEntry(
            format: MediaType::MEDIA_TYPE_APPLICATION_JSON,
            statusCode: $status,
            body: $parameter,
            headers: $headers,
        );
    }

    protected function getResponse(Reflection\Type $type, Entry $entry): ?JsonResponse
    {
        $class = Reflection::reflection($type->getType());

        $instance = $class->newInstanceWithoutConstructor();

        if ($instance instanceof ResourceCollection) {
            return $this->getResponseFromCollection($instance, $type);
        }
        if ($instance instanceof JsonResource) {
            return $this->getResponseFromResource($instance, $type, $class);
        }

        return null;
    }

    private function getResponseFromCollection(ResourceCollection $instance, Reflection\Type $type): ?JsonResponse
    {
        try {
            $resource = Reflection::read($instance, 'collects') ?? Reflection::call($instance, 'collects');

            if (!$resource || !Reflection::isInstantiable($resource)) {
                if(!($type->isGeneric() && ($resource = $type->getSub()) && Reflection::isInstantiable($resource))) {
                    return null;
                }

                $instance->collects = $resource;
            }

            $instance->collection = collect($this->getResource(new ReflectionClass($resource), 2))->mapInto($resource);

            return $instance->response();
        } catch (\Throwable $e) {
            dump($e->getMessage()); // TODO : suggestion
            return null;
        }
    }

    private function getResponseFromResource(JsonResource $instance,  Reflection\Type $type, ReflectionClass $class): ?JsonResponse
    {
        try {
            $instance->resource = $this->getResource($class);

            return $instance->response();
        } catch (\Throwable $e) {
            dump($e->getMessage()); // TODO : suggestion
            return null;
        }
    }

    private function getResource(ReflectionClass $class, int $count = 1): mixed
    {
        $resource = $this->getResourceType($class);

        if ($resource && method_exists($resource, 'factory')) {
            $factory = static fn() => $count > 1
                ? $resource::factory()->count($count)
                : $resource::factory();
            try {
                $resources = $factory()->create();
                collect($resources instanceof Collection ? $resources : [$resources])->map(fn($resource
                ) => $resource->wasRecentlyCreated = false);
                return $resources;
            } catch (QueryException $e) {
                return $factory()->make([
                    'id' => '',
                ]);
            }
        }

        $fake = static fn() => $resource
            ? new Fake($resource, [
                'id' => '',
                'type' => Str::kebab(Str::afterLast($resource, "\\"))
            ])
            : new Fake;

        if ($count === 1) {
            return $fake();
        }

        return collect(array_fill(0, $count, $fake()));
    }

    private function getResourceType(ReflectionClass $class): ?string
    {
        if ($type = $this->getResourceTypeFromConstructor($class)) {
            return $type;
        }

        if ($type = Reflection::getPropertyType($class->getName(), 'resource', false)) {
            return $type->getType();
        }

        if ($type = Reflection::tryParseGeneric($class, 'extends')) {
            return $type->getType();
        }

        return null;
    }

    protected function getResourceTypeFromConstructor(ReflectionClass $class): ?string
    {
        if ($constructor = $class->getConstructor()) {
            foreach ($constructor->getParameters() as $parameter) {
                if (Reflection::typeIsInstantiable($type = $parameter->getType())) {
                    return $type->getName();
                }

                break;
            }

            if (($doclbock = Reflection::docblock($constructor))
                && !empty($tags = $doclbock->getTagsWithTypeByName('params'))) {
                $tag = $tags[0];

                if ($type = Reflection::parseDoctag($tag)) {
                    return $type->getType();
                }
            }
        }

        return null;
    }
}
