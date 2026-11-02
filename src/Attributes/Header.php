<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Describes a request header of the actions of a controller, of every action using a form request, or of an action.
 *
 * It completes the parameter of the same name given by the rules or by an attribute before it (controller, then
 * request, then action): `type` replaces its schema, a `null` value leaves it unchanged.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Header
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
