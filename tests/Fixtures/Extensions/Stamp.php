<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Extensions;

/**
 * A dependency the container gives to the extensions.
 */
final readonly class Stamp
{
    public function __construct(
        public string $value = 'stamped',
    ) {}
}
