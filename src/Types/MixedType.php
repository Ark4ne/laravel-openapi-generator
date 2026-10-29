<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Types;

/**
 * `mixed`, and every type the resolver cannot express (unbound template, callable signature, conditional type…).
 */
final readonly class MixedType implements Type
{
    public function toString(): string
    {
        return 'mixed';
    }
}
