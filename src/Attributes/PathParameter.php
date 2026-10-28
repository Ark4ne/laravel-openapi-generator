<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Describes a path parameter of an action; the type is a PHPDoc type (`int`, `'draft'|'published'`).
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class PathParameter
{
    public function __construct(
        public string $name,
        public ?string $type = null,
        public ?string $description = null,
        public mixed $example = null,
    ) {}
}
