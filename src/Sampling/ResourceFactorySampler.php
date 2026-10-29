<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Attributes\ResourceFactory;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;
use ReflectionClass;
use Throwable;

/**
 * `#[ResourceFactory]` of the resource: a model factory class (`make()` with the parameters), or a class whose
 * `method` (or `__invoke`) receives the parameters by name. A factory that fails is reported; the next sampler is
 * then tried.
 */
final readonly class ResourceFactorySampler implements Sampler
{
    public const PRIORITY = 400;

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(SampleRequest $request, GenerationContext $context): bool
    {
        return self::attribute($request) !== null;
    }

    public function sample(SampleRequest $request, GenerationContext $context): array
    {
        $attribute = self::attribute($request);

        if ($attribute === null) {
            return [];
        }

        try {
            $samples = [];

            for ($i = 0; $i < $request->count; $i++) {
                $samples[] = self::build($attribute);
            }

            return $samples;
        } catch (Throwable $error) {
            $context->diagnostics->warning(Stage::Sampling, "#[ResourceFactory] of {$request->resource} failed: {$error->getMessage()}");

            return [];
        }
    }

    private static function build(ResourceFactory $attribute): mixed
    {
        $factory = $attribute->factory;

        if (is_subclass_of($factory, Factory::class)) {
            /** @var array<string, mixed> $attributes */
            $attributes = $attribute->parameters;

            return $factory::new()->make($attributes);
        }

        $instance = new $factory();
        $callable = $attribute->method === null ? $instance : [$instance, $attribute->method];

        if (! is_callable($callable)) {
            throw new LogicException(sprintf('%s::%s() cannot be called', $factory, $attribute->method ?? '__invoke'));
        }

        return $callable(...$attribute->parameters);
    }

    private static function attribute(SampleRequest $request): ?ResourceFactory
    {
        if ($request->resource === null || ! class_exists($request->resource)) {
            return null;
        }

        $attribute = (new ReflectionClass($request->resource))->getAttributes(ResourceFactory::class)[0] ?? null;

        return $attribute?->newInstance();
    }
}
