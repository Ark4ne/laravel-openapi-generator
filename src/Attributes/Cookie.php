<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Describes a cookie read by the actions of a controller, by every action using a form request, or by an action: no
 * validation rule describes one.
 *
 * It completes the cookie of the same name given before it (`DescribesRequest`, or an attribute of the controller,
 * then of the request, then of the action): `type` replaces its schema, a `null` value leaves it unchanged.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Cookie
{
    /**
     * @param string|null $type PHPDoc type of the value; `null` keeps the schema (a string for a new parameter)
     * @param bool|null $required `null` keeps it (not required for a new parameter)
     */
    public function __construct(
        public string $name,
        public ?string $type = null,
        public ?string $description = null,
        public ?bool $required = null,
        public mixed $example = null,
    ) {}
}
