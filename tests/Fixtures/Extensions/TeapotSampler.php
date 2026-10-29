<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Extensions;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Sampling\Sampler;
use Ark4ne\OpenApi\Sampling\SampleRequest;

/**
 * Samples teapots.
 */
final readonly class TeapotSampler implements Sampler
{
    public function priority(): int
    {
        return 1000;
    }

    public function supports(SampleRequest $request, GenerationContext $context): bool
    {
        return $request->target === Teapot::class;
    }

    public function sample(SampleRequest $request, GenerationContext $context): array
    {
        return array_fill(0, $request->count, new Teapot());
    }
}
