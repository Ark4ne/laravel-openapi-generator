<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

/**
 * What to sample: instances of a class (model, enum, any object), for a resource.
 */
final readonly class SampleRequest
{
    /**
     * @param class-string $target class of the instances
     * @param class-string|null $resource resource that receives them (`#[ResourceFactory]` is read on it)
     * @param int $count 1 for a resource, the configured collection size for a collection
     */
    public function __construct(
        public string $target,
        public ?string $resource = null,
        public int $count = 1,
    ) {}
}
