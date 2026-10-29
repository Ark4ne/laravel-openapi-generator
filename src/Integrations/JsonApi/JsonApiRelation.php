<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApi;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\JsonApi\Support\FakeModel;
use Closure;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * One relationship a JSON:API resource declares: the resource it relates to, one or many, its links and meta, and the
 * collection class that holds it, if any.
 */
final readonly class JsonApiRelation
{
    /**
     * @param class-string<JsonApiResource<mixed>> $related
     * @param class-string<ResourceCollection>|null $collection
     */
    public function __construct(
        public string $name,
        public string $related,
        public bool $many,
        public ?Closure $links = null,
        public ?Closure $meta = null,
        public ?string $collection = null,
    ) {}

    /**
     * What the package gives to the closures of the links and meta: the related resource, or an empty collection of it.
     */
    public function holder(): JsonResource
    {
        return match (true) {
            $this->collection !== null => new ($this->collection)([]),
            $this->many => $this->related::collection([]),
            default => new ($this->related)(new FakeModel()),
        };
    }
}
