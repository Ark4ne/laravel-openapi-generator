<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Sampling\ResourceModels;
use Ark4ne\OpenApi\Types\GenericType;
use Ark4ne\OpenApi\Types\NamedType;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * `ResourceCollection` subclasses and `AnonymousResourceCollection<UserResource>`: `data`, a list of the collected
 * resource. Paginated collections are declared by `#[Response(paginator: …)]` (DeclaredResponses).
 */
final readonly class ResourceCollectionExtractor implements ResponseExtractor
{
    public const PRIORITY = 320;

    public function __construct(
        private ResourceSchemas $resources,
        private ResourceModels $models,
    ) {}

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(Type $type, OperationContext $operation): bool
    {
        return self::collected($type) !== null || ($type instanceof NamedType && is_subclass_of($type->name, ResourceCollection::class));
    }

    public function extract(Type $type, OperationContext $operation, GenerationContext $context): array
    {
        $item = self::collected($type) ?? ($type instanceof NamedType && class_exists($type->name) ? $this->models->collected($type->name) : null);
        $items = $item === null ? Schema::create() : $this->resources->schema($item, $context);

        return [ResponseDescription::ok()->description('OK')->content('application/json', Paginations::object(['data' => Schema::array()->items($items)]))];
    }

    /**
     * The body of a collection class: `data`, a list of the resource it collects.
     *
     * @param class-string<ResourceCollection> $collection
     */
    public function collection(string $collection, GenerationContext $context): Schema
    {
        $item = $this->models->collected($collection);

        return Paginations::object(['data' => Schema::array()->items($item === null ? Schema::create() : $this->resources->schema($item, $context))]);
    }

    /**
     * The body of a paginated collection of a resource.
     *
     * @param class-string<JsonResource> $resource
     */
    public function paginated(string $resource, string $paginator, GenerationContext $context): Schema
    {
        return Paginations::resource($paginator, $this->resources->schema($resource, $context));
    }

    /**
     * The resource of a generic collection type (`AnonymousResourceCollection<UserResource>`).
     *
     * @return class-string<JsonResource>|null
     */
    private static function collected(Type $type): ?string
    {
        if (! $type instanceof GenericType || ! is_a($type->name, ResourceCollection::class, true)) {
            return null;
        }

        $item = $type->arguments[array_key_last($type->arguments)];

        return $item instanceof NamedType && is_subclass_of($item->name, JsonResource::class) ? $item->name : null;
    }
}
