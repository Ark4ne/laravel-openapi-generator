<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Requires a security scheme of the components; repeat it for alternatives.
 *
 * It replaces the security inferred from the middlewares.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Security
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public string $scheme,
        public array $scopes = [],
    ) {}
}
