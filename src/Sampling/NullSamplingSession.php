<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Context\GenerationContext;
use Closure;

/**
 * A session without database: nothing is ever saved, samples are made.
 */
final readonly class NullSamplingSession implements SamplingSession
{
    public function run(Closure $callback, GenerationContext $context): mixed
    {
        return $callback();
    }

    public function canPersist(?string $connection = null): bool
    {
        return false;
    }
}
