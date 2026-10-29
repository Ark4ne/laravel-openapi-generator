<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Describes a cookie read by an action, or by every action using a form request: no validation rule describes one.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Cookie
{
    public function __construct(
        public string $name,
        public ?string $type = null,
        public ?string $description = null,
        public bool $required = false,
        public mixed $example = null,
    ) {}
}
