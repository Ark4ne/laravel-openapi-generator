<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Tells how to build the sample a resource is documented with.
 *
 * `factory` is a class (called through `method`, or invoked) or a model factory class.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ResourceFactory
{
    /**
     * @param class-string $factory
     * @param array<array-key, mixed> $parameters
     */
    public function __construct(
        public string $factory,
        public array $parameters = [],
        public ?string $method = null,
    ) {}
}
