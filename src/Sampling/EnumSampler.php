<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Context\GenerationContext;

/**
 * The cases of an enum, in order, as many as asked.
 */
final readonly class EnumSampler implements Sampler
{
    public const PRIORITY = 200;

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(SampleRequest $request, GenerationContext $context): bool
    {
        return enum_exists($request->target) && $request->target::cases() !== [];
    }

    public function sample(SampleRequest $request, GenerationContext $context): array
    {
        if (! enum_exists($request->target)) {
            return [];
        }

        $cases = $request->target::cases();
        $samples = [];

        for ($i = 0; $i < $request->count; $i++) {
            $samples[] = $cases[$i % count($cases)];
        }

        return $samples;
    }
}
