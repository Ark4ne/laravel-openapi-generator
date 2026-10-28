<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The factory of a model (`HasFactory`, `#[UseFactory]`): `create()` inside a transaction of the session, so that
 * the samples have their keys and their defaults, else `make()`. Never anything kept.
 */
final readonly class ModelFactorySampler implements Sampler
{
    public const PRIORITY = 300;

    public function __construct(
        private SamplingSession $session,
    ) {}

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(SampleRequest $request, GenerationContext $context): bool
    {
        return is_subclass_of($request->target, Model::class) && method_exists($request->target, 'factory');
    }

    public function sample(SampleRequest $request, GenerationContext $context): array
    {
        $class = $request->target;

        try {
            // `factory()` comes from the HasFactory trait, unknown to Model.
            $callable = [$class, 'factory'];
            $factory = is_callable($callable) ? $callable() : null;

            if (! $factory instanceof Factory) {
                return [];
            }

            $factory = $factory->count($request->count);
            $model = $factory->newModel();
            $samples = $this->session->canPersist($model->getConnectionName()) ? self::created($factory) : null;

            return $samples ?? self::models($factory->make());
        } catch (Throwable $error) {
            $context->diagnostics->warning(Stage::Sampling, "the factory of {$class} failed: {$error->getMessage()}");

            return [];
        }
    }

    /**
     * @param Factory<Model> $factory
     *
     * @return list<Model>|null `null` when the models cannot be saved (missing table, constraint…)
     */
    private static function created(Factory $factory): ?array
    {
        try {
            return self::models($factory->create());
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<Model>
     */
    private static function models(mixed $made): array
    {
        return match (true) {
            $made instanceof Model => [$made],
            $made instanceof Collection => array_values(array_filter($made->all(), static fn(mixed $model): bool => $model instanceof Model)),
            default => [],
        };
    }
}
