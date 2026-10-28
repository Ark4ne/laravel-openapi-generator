<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Sampling\SampleRequest;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Sampling\SchemaInferrer;
use Ark4ne\OpenApi\Sampling\TypeSchemas;
use Ark4ne\OpenApi\Types\GenericType;
use Ark4ne\OpenApi\Types\ListType;
use Ark4ne\OpenApi\Types\NamedType;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Traversable;

/**
 * The schema of a value returned as it is (model, collection, list, shape): a resource is its component, a model its
 * sampled attributes, a collection or a list its items; anything else its declared type.
 */
final readonly class ValueSchemas
{
    public function __construct(
        private ResourceSchemas $resources,
        private Samplers $samplers,
        private SchemaInferrer $inferrer,
    ) {}

    public function of(Type $type, GenerationContext $context): Schema
    {
        return match (true) {
            $type instanceof NamedType && is_subclass_of($type->name, JsonResource::class) => $this->resources->schema($type->name, $context),
            $type instanceof NamedType && is_subclass_of($type->name, Model::class) => $this->model($type->name, $context),
            $type instanceof ListType => Schema::array()->items($this->of($type->value, $context)),
            $type instanceof GenericType && (is_a($type->name, Traversable::class, true) || in_array($type->name, ['array', 'iterable'], true)) && ! self::isMap($type) => Schema::array()->items($this->of($type->arguments[array_key_last($type->arguments)], $context)),
            default => TypeSchemas::of($type),
        };
    }

    /**
     * @param class-string<Model> $class
     */
    private function model(string $class, GenerationContext $context): Schema
    {
        $sample = $this->samplers->sample(new SampleRequest($class), $context)[0] ?? null;

        return $sample === null ? Schema::object() : $this->inferrer->infer($sample, $context);
    }

    private static function isMap(GenericType $type): bool
    {
        $key = count($type->arguments) > 1 ? $type->arguments[0] : null;

        return in_array($type->name, ['array', 'iterable'], true) && $key instanceof NamedType && $key->name === 'string';
    }
}
