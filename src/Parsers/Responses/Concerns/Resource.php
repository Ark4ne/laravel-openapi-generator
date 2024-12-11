<?php

namespace Ark4ne\OpenApi\Parsers\Responses\Concerns;

use Ark4ne\OpenApi\Contracts\Entry;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Documentation\ResponseEntry;
use Ark4ne\OpenApi\Support\Facades\Logger;
use Ark4ne\OpenApi\Support\Fake;
use Ark4ne\OpenApi\Support\Reflection;
use Ark4ne\OpenApi\Support\Support;
use GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

trait Resource
{
    use Response;

    /**
     * @param \Ark4ne\OpenApi\Support\Reflection\Type<JsonResource|ResourceCollection> $element
     * @param \Ark4ne\OpenApi\Contracts\Entry                                          $entry
     *
     * @return ResponseEntry
     */
    public function parse(Reflection\Type $element, Entry $entry): ResponseEntry
    {
        return $this->toResponseEntry($this->getResponse($element, $entry), $entry);
    }

    protected function toResponseEntry(?SymfonyResponse $response, Entry $entry, $body = null): ResponseEntry
    {
        $status = $entry->getDocResponseStatusCode() ?? 200;
        $statusText = $entry->getDocResponseStatusName() ?? SymfonyResponse::$statusTexts[200];
        $headers = iterator_to_array($entry->getDocResponseHeaders());

        if ($response) {
            $status = $entry->getDocResponseStatusCode() ?? $response->getStatusCode();
            $statusText = $entry->getDocResponseStatusName() ?? Reflection::read($response, 'statusText');
            $body ??= Parameter::fromJson($response->getData(true));
            $headers = array_merge($headers, $response->headers->allPreserveCase());
        }

        return new ResponseEntry(
            format: MediaType::MEDIA_TYPE_APPLICATION_JSON,
            statusCode: $status,
            statusName: $statusText,
            headers: $this->convertHeadersToOasHeaders($headers),
            body: $body,
        );
    }

    protected function getResponse(Reflection\Type $type, Entry $entry): ?JsonResponse
    {
        $class = Reflection::reflection($type->getType());

        $instance = $class->newInstanceWithoutConstructor();

        if ($instance instanceof ResourceCollection) {
            return $this->getResponseFromCollection($entry, $instance, $type);
        }
        if ($instance instanceof JsonResource) {
            return $this->getResponseFromResource($entry, $instance, $type, $class);
        }

        return null;
    }

    private function getResponseFromCollection(
        Entry $entry,
        ResourceCollection $instance,
        Reflection\Type $type
    ): ?JsonResponse {
        try {
            if (!($resource = $this->getResourceFromCollection($instance, $type))) {
                Logger::error("Can't determinate resource type for collection : " . $instance::class);
                return null;
            }

            $instance->collects = $resource;
            $collection = collect($this->getModelFromResource(new ReflectionClass($resource), 2))->mapInto($resource);

            if ($entry->getDocResponsePaginate()) {
                $collection = new LengthAwarePaginator($collection, $collection->count(), 15);
            }

            $instance->collection = $collection;

            return $instance->response();
        } catch (\Throwable $e) {
            Logger::error([
                "Error when trying to documentate resource collection " . $instance::class,
                $e->getMessage()
            ]);
            return null;
        }
    }

    private function getResponseFromResource(
        Entry $entry,
        JsonResource $instance,
        Reflection\Type $type,
        ReflectionClass $class
    ): ?JsonResponse {
        try {
            $instance->resource = $this->getModelFromResource($class);

            return $instance->response();
        } catch (\Throwable $e) {
            Logger::error([
                "Error when trying to documentate resource " . $instance::class,
                $e->getMessage()
            ]);
            return null;
        }
    }

    private function getResourceFromCollection(ResourceCollection $instance, ?Reflection\Type $type): ?string
    {
        $resource = Reflection::read($instance, 'collects') ?? Reflection::call($instance, 'collects');

        if (!$resource || !Reflection::isInstantiable($resource)) {
            if (!($type && $type->isGeneric() && ($resource = $type->getSub()) && Reflection::isInstantiable($resource))) {
                return null;
            }
        }

        return $resource;
    }

    private function getModelFromResource(ReflectionClass $class, int $count = 1): mixed
    {
        $resourceClass = $resource = $this->getResourceClass($class);

        if ($resource && Support::method($resource, 'factory')) {
            $factory = static fn() => $count > 1
                ? $resource::factory()->count($count)
                : $resource::factory();
            if (config('openapi.connections.use-transaction')) {
                try {
                    $resources = $factory()->create();
                    collect($resources instanceof Collection ? $resources : [$resources])->map(fn($resource
                    ) => $resource->wasRecentlyCreated = false);
                    return $resources;
                } catch (QueryException $e) {
                    Logger::warn(["Can create concrete model [$resourceClass] with factory::create.", $e->getMessage()]);
                    Logger::notice("Use factory::make instead.");
                }
            }
            try {
                return $factory()->make([
                    'id' => 'mixed',
                    'uuid' => 'mixed',
                ]);
            } catch (Throwable $e) {
                Logger::warn(["Can create model [$resourceClass] with factory::make.", $e->getMessage()]);
                Logger::notice("Try use faker instead.");
            }
        }

        $fake = static fn() => $resource
            ? new Fake($resource, [
                'id' => 'mixed',
                'uuid' => 'mixed',
                'type' => Str::kebab(Str::afterLast($resource, "\\"))
            ])
            : new Fake;

        if ($count === 1) {
            return $fake();
        }

        return collect(array_fill(0, $count, $fake()));
    }

    private function getResourceClass(ReflectionClass $class): ?string
    {
        if ($type = $this->getResourceTypeFromConstructor($class)) {
            return $type;
        }

        if ($type = Reflection::getPropertyType($class->getName(), 'resource', false)) {
            return $type->getType();
        }

        if ($type = Reflection::tryParseGeneric($class, 'extends')) {
            if ($type->isGeneric()) {
                return $type->getSub();
            }

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
