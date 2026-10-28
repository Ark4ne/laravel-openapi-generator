<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Support\DateFormats;
use Ark4ne\OpenApi\Support\Enums;
use Ark4ne\OpenApi\Types\ArrayShapeType;
use Ark4ne\OpenApi\Types\GenericType;
use Ark4ne\OpenApi\Types\ListType;
use Ark4ne\OpenApi\Types\NullableType;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApiWriter\Objects\Schema;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Http\Resources\PotentiallyMissing;
use Illuminate\Support\Str;
use JsonSerializable;

/**
 * Infers a schema from a sampled value, completed by its declared type when there is one.
 *
 * - The declared type wins on the type of a scalar or of `null`: a `null` sample does not make a field only `null`.
 * - Lists take the schema of their first item (an empty list, the declared item type); arrays with keys are objects,
 *   each key required unless its value is missing (`when()`, `whenLoaded()`…).
 * - A model is its attributes, typed by ModelSchemas; a nested resource is described by the ResourceDescriber
 *   (as a returned resource), else becomes a component from one run, referenced by `$ref`.
 * - Scalars carry their value as example, unless `sampling.examples` is off.
 */
final readonly class SchemaInferrer
{
    public function __construct(
        private ModelSchemas $models,
        private ?ResourceDescriber $resources = null,
    ) {}

    /**
     * This inferrer, describing the nested resources with the given describer.
     */
    public function withResources(ResourceDescriber $resources): self
    {
        return new self($this->models, $resources);
    }

    public function infer(mixed $value, GenerationContext $context, ?Type $declared = null): Schema
    {
        $schema = $declared === null ? null : TypeSchemas::of($declared);

        return match (true) {
            $value instanceof PotentiallyMissing, $value === null, self::missing($value) => $schema ?? Schema::create(),
            is_scalar($value) => $this->example($schema ?? self::scalar($value, $context), $value, $context),
            $value instanceof BackedEnum => $this->example(Schema::create()->type(Enums::type($value::class))->enum(...Enums::values($value::class)), $value->value, $context),
            $value instanceof DateTimeInterface => $this->example($schema ?? Schema::string()->format('date-time'), json_decode((string) json_encode($value)), $context),
            $value instanceof JsonResource => $this->resource($value, $context),
            $value instanceof Model => $this->model($value, $context),
            $value instanceof Arrayable => $this->infer($value->toArray(), $context, $declared),
            $value instanceof JsonSerializable => $this->infer($value->jsonSerialize(), $context, $declared),
            is_array($value) => $this->array($value, $context, $declared),
            default => $schema ?? Schema::object(),
        };
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private function array(array $value, GenerationContext $context, ?Type $declared): Schema
    {
        $value = self::merged($value);
        $inner = $declared instanceof NullableType ? $declared->type : $declared;

        if (array_is_list($value)) {
            $item = match (true) {
                $inner instanceof ListType => $inner->value,
                $inner instanceof GenericType => $inner->arguments[array_key_last($inner->arguments)],
                default => null,
            };

            if ($value === []) {
                return $item === null ? Schema::array() : Schema::array()->items(TypeSchemas::of($item));
            }

            return Schema::array()->items($this->infer($value[0], $context, $item));
        }

        $types = [];

        if ($inner instanceof ArrayShapeType) {
            foreach ($inner->items as $shapeItem) {
                $types[(string) $shapeItem->key] = $shapeItem->type;
            }
        }

        $schema = Schema::object();
        $required = [];

        foreach ($value as $key => $property) {
            $key = (string) $key;
            $schema = $schema->addProperties($this->infer($property, $context, $types[$key] ?? null)->objectId($key));

            if (! ($property instanceof PotentiallyMissing && $property->isMissing()) && ! self::missing($property)) {
                $required[] = $key;
            }
        }

        return $required === [] ? $schema : $schema->required(...$required);
    }

    private function model(Model $model, GenerationContext $context): Schema
    {
        $declared = $this->models->of($model);
        $schema = Schema::object();
        $required = [];

        foreach ($model->toArray() as $key => $property) {
            $key = (string) $key;
            $inferred = isset($declared[$key])
                ? $this->example($declared[$key], is_scalar($property) ? $property : null, $context)
                : $this->infer($property, $context);
            $schema = $schema->addProperties($inferred->objectId($key));
            $required[] = $key;
        }

        return $required === [] ? $schema : $schema->required(...$required);
    }

    private function resource(JsonResource $resource, GenerationContext $context): Schema
    {
        if ($this->resources !== null) {
            return $this->resources->describe($resource, $context);
        }

        $class = $resource::class;
        $name = $context->components->nameOf($class);

        if ($name === null) {
            // Registered before its schema is inferred, so that a resource nested in itself is a reference.
            $name = $context->components->register($class, ComponentNames::of($class), Schema::object());
            $context->components->register($class, $name, $this->infer($resource->toArray(Request::create('/')), $context));
        }

        return $context->document->useReferences ? Schema::ref($name) : $context->components->get($name) ?? Schema::object();
    }

    /**
     * A resource around a missing value (`UserResource::make($this->whenLoaded('user'))`): left out by Laravel.
     */
    private static function missing(mixed $value): bool
    {
        return $value instanceof JsonResource && $value->resource instanceof PotentiallyMissing && $value->resource->isMissing();
    }

    private static function scalar(int|float|string|bool $value, GenerationContext $context): Schema
    {
        return match (true) {
            is_int($value) => Schema::integer(),
            is_float($value) => Schema::number(),
            is_bool($value) => Schema::boolean(),
            Str::isUuid($value) => Schema::string()->format('uuid'),
            default => ($format = DateFormats::of($value, $context->configuration->dateFormats)) === null ? Schema::string() : Schema::string()->format($format),
        };
    }

    private function example(Schema $schema, mixed $value, GenerationContext $context): Schema
    {
        return $value === null || ! $context->configuration->sampling->examples ? $schema : $schema->example($value);
    }

    /**
     * The values of `merge()` / `mergeWhen()` brought up to the level of the array that holds them.
     *
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private static function merged(array $value): array
    {
        $merged = [];

        foreach ($value as $key => $item) {
            if ($item instanceof MergeValue) {
                $merged = [...$merged, ...$item->data];
            } else {
                $merged[$key] = $item;
            }
        }

        return $merged;
    }
}
