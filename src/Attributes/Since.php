<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;
use UnitEnum;

/**
 * The first variant of the document (`documents.<name>.variants`) that has an action, or every action of a controller:
 * the documents of older variants leave it out. Inclusive: the document of `$variant` lists it.
 *
 * The attribute of the action wins over that of the controller; `#[Until]` is read independently. Without `variants` on
 * the document, it is ignored. A variant the document does not declare is a warning, the operation kept.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class Since
{
    /**
     * @param UnitEnum|string $variant an enum case (its value when backed, else its name) or the name of a variant
     */
    public function __construct(
        public UnitEnum|string $variant,
    ) {}
}
