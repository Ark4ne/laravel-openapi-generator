<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Types\TypeResolver;

/**
 * The sampler chain: `#[ResourceFactory]` → model factory → enum cases → fake object.
 */
final class Samplers
{
    /** @var list<Sampler> */
    private readonly array $samplers;

    public function __construct(Sampler ...$samplers)
    {
        $samplers = array_values($samplers);
        usort($samplers, static fn(Sampler $a, Sampler $b): int => $b->priority() <=> $a->priority());
        $this->samplers = $samplers;
    }

    /**
     * These samplers with others, sorted together by priority.
     */
    public function with(Sampler ...$others): self
    {
        return new self(...$this->all(), ...$others);
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
        foreach ($this->samplers as $sampler) {
            if ($sampler->supports($request, $context)) {
                $samples = $sampler->sample($request, $context);

                // A sampler that fails gives nothing: the next one is tried.
                if ($samples !== []) {
                    return $samples;
                }
            }
        }

        return null;
    }

    /**
     * The built-in chain.
     */
    public static function default(SamplingSession $session, TypeResolver $types): self
    {
        return new self(
            new ResourceFactorySampler(),
            new ModelFactorySampler($session),
            new EnumSampler(),
            new FakeObjectSampler(new FakeValues($types)),
        );
    }
}
