<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApi;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Closure;

/**
 * One relationship a JSON:API resource declares: the resource it relates to, one or many, its links and meta.
 */
final readonly class JsonApiRelation
{
    /**
     * @param class-string<JsonApiResource<mixed>> $related
     */
    public function __construct(
        public string $name,
        public string $related,
        public bool $many,
        public ?Closure $links = null,
        public ?Closure $meta = null,
    ) {}
}
