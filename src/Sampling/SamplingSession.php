<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

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
    public function run(Closure $callback): mixed;

    /**
     * Whether the sampled models may be saved: `false` outside a transaction, where only `make()` is used.
     */
    public function canPersist(): bool;
}
