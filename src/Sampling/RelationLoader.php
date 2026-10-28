<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Context\GenerationContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * A copy of a sampled model with its relations loaded from samples of the related models, one level deep: the
 * relations declared by a method whose native return type is a Relation and that takes no argument.
 *
 * A relation to one model gets one sample, a relation to many gets a collection; a polymorphic `MorphTo`, whose
 * related model is not known, is left unloaded.
 */
final readonly class RelationLoader
{
    private const SINGLE = [BelongsTo::class, HasOne::class, HasOneThrough::class, MorphOne::class];

    public function __construct(
        private Samplers $samplers,
    ) {}

    public function load(Model $model, GenerationContext $context): Model
    {
        $loaded = clone $model;

        foreach (self::relations($model) as $name) {
            try {
                $method = [$model, $name];
                $relation = is_callable($method) ? $method() : null;

                if (! $relation instanceof Relation || $relation instanceof MorphTo) {
                    continue;
                }

                $single = array_filter(self::SINGLE, static fn(string $class): bool => $relation instanceof $class) !== [];
                $samples = $this->samplers->sample(new SampleRequest($relation->getRelated()::class, count: $single ? 1 : $context->configuration->sampling->collectionSize), $context) ?? [];
                $related = array_values(array_filter($samples, static fn(mixed $sample): bool => $sample instanceof Model));
                $loaded->setRelation($name, $single ? ($related[0] ?? null) : new Collection($related));
            } catch (Throwable) {
                // A relation that cannot be built stays unloaded.
            }
        }

        return $loaded;
    }

    /**
     * @return list<string>
     */
    private static function relations(Model $model): array
    {
        $relations = [];

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $type = $method->getReturnType();

            if (! $method->isStatic() && $method->getNumberOfRequiredParameters() === 0 && $type instanceof ReflectionNamedType && is_a($type->getName(), Relation::class, true)) {
                $relations[] = $method->getName();
            }
        }

        return $relations;
    }
}
