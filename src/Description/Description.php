<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Description;

/**
 * Base of the description model: immutable value objects built by named constructors.
 *
 * Every setter returns `copy()` of the object with the changed properties; the object itself never changes.
 */
abstract readonly class Description
{
    /**
     * A copy of this object with the given promoted properties replaced, by name.
     *
     * Every property of a description is promoted by its constructor, so the constructor accepts all of them by name.
     */
    protected function copy(mixed ...$changes): static
    {
        return new static(...[...get_object_vars($this), ...$changes]);
    }
}
