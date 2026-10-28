<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Adds a tag to an operation, or to every action of a controller.
 *
 * `parent` groups tags: written as `Tag.parent` in 3.2, as the `x-tagGroups` extension before.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Tag
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $parent = null,
    ) {}
}
