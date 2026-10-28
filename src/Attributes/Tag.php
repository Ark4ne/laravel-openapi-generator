<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Adds a tag to an operation, or to every action of a controller.
 *
 * `parent` groups tags: written as `Tag.parent` in 3.2, as the `x-tagGroups` extension before.
 * `summary` and `kind` (`nav`, `badge`, `audience`…) are written from 3.2 only.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Tag
{
    /**
     * @param string|null $externalDocs URL of the external documentation
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $parent = null,
        public ?string $summary = null,
        public ?string $kind = null,
        public ?string $externalDocs = null,
    ) {}
}
