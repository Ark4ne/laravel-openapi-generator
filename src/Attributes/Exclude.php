<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Leaves an action, every action of a controller, or a route closure out of the documents.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
final readonly class Exclude {}
