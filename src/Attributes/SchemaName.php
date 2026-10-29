<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Name of the schema component of a resource or an enum (`components.schemas.<name>`).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class SchemaName
{
    public function __construct(public string $name) {}
}
