<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Context\GenerationContext;

/**
 * The sampler chain: `#[ResourceFactory]` → model factory → enum cases → fake object.
 */
final class Samplers
{
    /** @var list<Sampler> */
    private readonly array $samplers;

    public function __construct(Sampler ...$samplers)
    {
        $this->samplers = array_values($samplers);
    }

    /**
     * @return list<Sampler> by decreasing priority
     */
    public function all(): array
    {
        return $this->samplers;
    }

    /**
     * @return list<mixed>|null `null` when no sampler supports the request
     */
    public function sample(SampleRequest $request, GenerationContext $context): ?array
    {
        return null;
    }
}
