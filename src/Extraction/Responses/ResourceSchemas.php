<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Attributes\ResourceFactory;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Sampling\ComponentNames;
use Ark4ne\OpenApi\Sampling\RelationLoader;
use Ark4ne\OpenApi\Sampling\ResourceModels;
use Ark4ne\OpenApi\Sampling\SampleRequest;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Sampling\SchemaInferrer;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

/**
 * The schema of a resource, from a sample of what it receives.
 *
 * `toArray()` runs twice: on the sample, then on the sample with its relations loaded (RelationLoader). A key of both
 * runs is required; a key of the second only (`whenLoaded()`) is optional, but described. The resource is a
 * component (`#[SchemaName]`, or its name without `Resource`), referenced by `$ref` unless `use_references` is off.
 */
final class ResourceSchemas
{
    /** @var array<string, array<string, mixed>> component → top-level property → sampled scalar value */
    private array $values = [];

    /** @var array<class-string, true> resources whose schema was computed */
    private array $described = [];

    public function __construct(
        private readonly ResourceModels $models,
        private readonly Samplers $samplers,
        private readonly SchemaInferrer $inferrer,
        private readonly RelationLoader $relations,
    ) {}

    /**
     * @param class-string<JsonResource> $resource
     */
    public function schema(string $resource, GenerationContext $context): Schema
    {
        $name = $context->components->nameOf($resource) ?? $context->components->register($resource, ComponentNames::of($resource), Schema::object());

        if (! isset($this->described[$resource])) {
            // Marked first: a resource nested in itself is then a reference.
            $this->described[$resource] = true;
            $context->components->register($resource, $name, $this->describe($resource, $name, $context));
        }

        return $context->document->useReferences ? Schema::ref($name) : $context->components->get($name) ?? Schema::object();
    }

    /**
     * The response body of a resource: its schema under `$wrap` (`data`), with `with()` and `$additional`.
     *
     * @param class-string<JsonResource> $resource
     */
    public function envelope(string $resource, Schema $inner, GenerationContext $context): Schema
    {
        $wrap = (new ReflectionProperty($resource, 'wrap'))->getValue();
        $properties = is_string($wrap) && $wrap !== '' ? [$wrap => $inner] : [];
        $instance = $this->instance($resource, $this->sample($resource, $context));

        if ($instance !== null) {
            try {
                foreach ([...$instance->with(Request::create('/')), ...$instance->additional] as $key => $value) {
                    $properties[(string) $key] = $this->inferrer->infer($value, $context);
                }
            } catch (Throwable) {
                // with() that fails adds nothing.
            }
        }

        return $properties === [] ? $inner : Paginations::object($properties);
    }

    /**
     * The value a component's property took in its sample, for the discriminators.
     */
    public function value(string $component, string $property): mixed
    {
        return $this->values[$component][$property] ?? null;
    }

    /**
     * @param class-string<JsonResource> $resource
     */
    private function describe(string $resource, string $name, GenerationContext $context): Schema
    {
        $sample = $this->sample($resource, $context);
        $request = Request::create('/');

        try {
            $plain = $this->instance($resource, $sample)?->toArray($request) ?? [];
            $schema = $this->inferrer->infer($plain, $context);
            $this->values[$name] = [];

            foreach (is_array($plain) ? $plain : [] as $key => $value) {
                if (is_scalar($value)) {
                    $this->values[$name][(string) $key] = $value;
                }
            }

            if ($sample instanceof Model) {
                $loaded = $this->instance($resource, $this->relations->load($sample, $context))?->toArray($request) ?? [];
                $schema = self::union($schema, $this->inferrer->infer($loaded, $context));
            }

            return $schema;
        } catch (Throwable $error) {
            $context->diagnostics->warning(Stage::Response, "cannot run {$resource}::toArray(): {$error->getMessage()}");

            return Schema::object();
        }
    }

    /**
     * A sample of what the resource receives: its model, or what its #[ResourceFactory] builds; `null` otherwise.
     *
     * @param class-string<JsonResource> $resource
     */
    private function sample(string $resource, GenerationContext $context): mixed
    {
        $model = $this->models->model($resource);
        $factory = (new ReflectionClass($resource))->getAttributes(ResourceFactory::class) !== [];

        if ($model === null && ! $factory) {
            return null;
        }

        return $this->samplers->sample(new SampleRequest($model ?? $resource, $resource), $context)[0] ?? null;
    }

    /**
     * @param class-string<JsonResource> $resource
     */
    private function instance(string $resource, mixed $sample): ?JsonResource
    {
        try {
            return new $resource($sample);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The properties of both schemas (the second's win), required in both.
     */
    public static function union(Schema $first, Schema $second): Schema
    {
        $properties = [...$first->properties ?? [], ...$second->properties ?? []];

        if ($properties === []) {
            return $second;
        }

        $required = array_values(array_intersect($first->required ?? [], $second->required ?? []));
        $schema = Schema::object()->properties(...array_values($properties));

        return $required === [] ? $schema : $schema->required(...$required);
    }
}
