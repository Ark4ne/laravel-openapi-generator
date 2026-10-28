<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Types\NamedType;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

/**
 * The last resort: a model filled from its declared types, or a FakeObject for any other concrete class.
 */
final readonly class FakeObjectSampler implements Sampler
{
    public const PRIORITY = 100;

    public function __construct(
        private FakeValues $values,
    ) {}

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(SampleRequest $request, GenerationContext $context): bool
    {
        return class_exists($request->target) && ! (new ReflectionClass($request->target))->isAbstract();
    }

    public function sample(SampleRequest $request, GenerationContext $context): array
    {
        $samples = [];

        for ($i = 0; $i < $request->count; $i++) {
            $sample = is_subclass_of($request->target, Model::class)
                ? $this->values->model($request->target)
                : $this->values->for(new NamedType($request->target));

            if ($sample !== null) {
                $samples[] = $sample;
            }
        }

        return $samples;
    }
}
