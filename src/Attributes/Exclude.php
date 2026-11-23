<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Leaves an action, every action of a controller, a route closure, or a payload webhook (a `JsonResource` marked
 * `#[Webhook]`) out of every document.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
final readonly class Exclude {}
