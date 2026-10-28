<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Context\GenerationContext;

/**
 * Produces representative instances of a class, without keeping any data.
 */
interface Sampler
{
    /**
     * Samplers are tried by decreasing priority; the first that supports the request is used.
     */
    public function priority(): int;

    public function supports(SampleRequest $request, GenerationContext $context): bool;

    /**
     * @return list<mixed> `$request->count` instances
     */
    public function sample(SampleRequest $request, GenerationContext $context): array;
}
