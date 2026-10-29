<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Describes a request header of an action, or of every action using a form request.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Header
{
    public function __construct(
        public string $name,
        public ?string $type = null,
        public ?string $description = null,
        public bool $required = false,
        public mixed $example = null,
    ) {}
}
