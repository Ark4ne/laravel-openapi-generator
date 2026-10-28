<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Context\GenerationContext;
use Closure;

/**
 * Runs a generation inside transactions that are always rolled back (Unit of Work).
 */
interface SamplingSession
{
    /**
     * @template T
     *
     * @param Closure(): T $callback
     *
     * @return T
     */
    public function run(Closure $callback, GenerationContext $context): mixed;

    /**
     * Whether the models of a connection (the default one when `null`) may be saved: `false` outside a transaction,
     * where only `make()` is used.
     */
    public function canPersist(?string $connection = null): bool;
}
