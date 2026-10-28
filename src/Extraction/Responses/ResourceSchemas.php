<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Attributes\ResourceFactory;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Sampling\ComponentNames;
use Ark4ne\OpenApi\Sampling\RelationLoader;
use Ark4ne\OpenApi\Sampling\ResourceDescriber;
use Ark4ne\OpenApi\Sampling\ResourceModels;
use Ark4ne\OpenApi\Sampling\SampleRequest;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Sampling\SchemaInferrer;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use ReflectionClass;
use ReflectionProperty;
use Throwable;
use WeakMap;

/**
 * The schema of a resource, from samples of what it receives; the only way a resource is described, returned or
 * nested in another (the SchemaInferrer delegates to it).
 *
 * - `toArray()` runs on a sample, then on the sample with its relations loaded (RelationLoader): a key of both runs is
 *   required, a key of the second only (`whenLoaded()`) optional, but described. A failing second run keeps the first.
 * - A second sample tells the values that do not change from one sample to the other (`type: cat`): the discriminators.
 * - The resource is a component (`#[SchemaName]`, or its name without `Resource`), referenced by `$ref` unless
 *   `use_references` is off. What is learnt is kept per generation context: each document gets its components.
 * - A resource a format supports (JSON:API) is written by that format: its component, its body, its collections and
 *   their media type.
 */
final class ResourceSchemas implements ResourceDescriber
{
    private readonly SchemaInferrer $inferrer;

    /** @var WeakMap<GenerationContext, array{described: array<string, true>, values: array<string, array<string, mixed>>, samples: array<string, list<mixed>>}> */
    private WeakMap $states;

    /**
     * @param list<ResourceFormat> $formats the first that supports a resource writes it
     */
    public function __construct(
        private readonly ResourceModels $models,
        private readonly Samplers $samplers,
        SchemaInferrer $inferrer,
        private readonly RelationLoader $relations,
        private readonly array $formats = [],
    ) {
        $this->inferrer = $inferrer->withResources($this);
        $this->states = new WeakMap();
    }

    /**
     * The inferrer that describes nested resources through this object.
     */
    public function inferrer(): SchemaInferrer
    {
        return $this->inferrer;
    }

    /**
     * What a resource receives and what a collection collects, for the formats.
     */
    public function models(): ResourceModels
    {
        return $this->models;
    }

    /**
     * The format that writes a resource, or the resource a collection collects; `null` for Laravel's.
     *
     * @param class-string<JsonResource> $resource
     */
    public function format(string $resource): ?ResourceFormat
    {
        if (is_subclass_of($resource, ResourceCollection::class)) {
            $collected = $this->models->collected($resource);

            return $collected === null ? null : $this->format($collected);
        }

        foreach ($this->formats as $format) {
            if ($format->supports($resource)) {
                return $format;
            }
        }

        return null;
    }

    /**
     * The media type of the bodies of a resource or of a collection: its format's, `application/json` otherwise.
     *
     * @param class-string<JsonResource> $resource
     */
    public function mediaType(string $resource): string
    {
        return $this->format($resource)?->mediaType() ?? 'application/json';
    }

    /**
     * The body of a list of a resource, as a resource collection writes it: `data`, with `links` and `meta` when
     * paginated. A collection class gives the list of the resource it collects.
     *
     * @param class-string<JsonResource> $resource
     */
    public function collection(string $resource, ?string $paginator, GenerationContext $context): Schema
    {
        if (is_subclass_of($resource, ResourceCollection::class)) {
            $collected = $this->models->collected($resource);

            return $collected === null ? Paginations::object(['data' => Schema::array()->items(Schema::create())]) : $this->collection($collected, $paginator, $context);
        }

        $data = $this->schema($resource, $context);
        $format = $this->format($resource);

        return match (true) {
            $format !== null => $format->collection($resource, $data, $paginator, $this, $context),
            $paginator !== null => Paginations::resource($paginator, $data),
            default => Paginations::object(['data' => Schema::array()->items($data)]),
        };
    }

    /**
     * The body of a request that sends a resource: the resource itself, or as its format sends it.
     *
     * @param class-string<JsonResource> $resource
     */
    public function request(string $resource, GenerationContext $context): Schema
    {
        $data = $this->schema($resource, $context);

        return $this->format($resource)?->request($resource, $data, $this, $context) ?? $data;
    }

    public function describe(JsonResource $resource, GenerationContext $context): Schema
    {
        if (! $resource instanceof ResourceCollection) {
            return $this->schema($resource::class, $context);
        }

        // Declared `string`, `$collects` is null when a collection relies on the naming convention.
        $collected = (new ReflectionProperty($resource, 'collects'))->getValue($resource) ?? $this->models->collected($resource::class);

        return Schema::array()->items(is_string($collected) && is_subclass_of($collected, JsonResource::class) ? $this->schema($collected, $context) : Schema::create());
    }

    /**
     * @param class-string<JsonResource> $resource
     */
    public function schema(string $resource, GenerationContext $context): Schema
    {
        $name = $context->components->nameOf($resource) ?? $context->components->register($resource, ComponentNames::of($resource), Schema::object());
        $state = $this->state($context);

        if (! isset($state['described'][$resource])) {
            // Marked first: a resource nested in itself is then a reference.
            $state['described'][$resource] = true;
            $this->states[$context] = $state;
            $context->components->register($resource, $name, $this->run($resource, $name, $context));
        }

        return $context->document->useReferences ? Schema::ref($name) : $context->components->get($name) ?? Schema::object();
    }

    /**
     * The response body of a resource, as `ResourceResponse::wrap()` builds it: the data under `$wrap` unless they
     * already hold that key, under `data` when there is no wrapper but additional information; then `with()` and
     * `$additional`.
     *
     * @param class-string<JsonResource> $resource
     */
    public function envelope(string $resource, Schema $inner, GenerationContext $context): Schema
    {
        $format = $this->format($resource);

        if ($format !== null) {
            return $format->document($resource, $inner, $this, $context);
        }

        $wrap = (new ReflectionProperty($resource, 'wrap'))->getValue();
        $wrap = is_string($wrap) && $wrap !== '' ? $wrap : null;
        $extras = $this->extras($resource, $context);
        $data = self::resolved($inner, $context);
        $holdsWrapper = $wrap !== null && isset($data->properties[$wrap]);

        if ($extras === []) {
            return $wrap === null || $holdsWrapper ? $inner : Paginations::object([$wrap => $inner]);
        }

        if ($holdsWrapper) {
            return ResourceSchemas::union($data, Paginations::object($extras));
        }

        return Paginations::object([$wrap ?? 'data' => $inner, ...$extras]);
    }

    /**
     * A value of a component's property that did not change from one sample to the other.
     */
    public function value(string $component, string $property, GenerationContext $context): mixed
    {
        return $this->state($context)['values'][$component][$property] ?? null;
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

    /**
     * @param class-string<JsonResource> $resource
     */
    private function run(string $resource, string $name, GenerationContext $context): Schema
    {
        $format = is_subclass_of($resource, ResourceCollection::class) ? null : $this->format($resource);

        if ($format !== null) {
            try {
                return $format->component($resource, $this, $context);
            } catch (Throwable $error) {
                $context->diagnostics->warning(Stage::Response, "cannot describe {$resource}: {$error->getMessage()}");

                return Schema::object();
            }
        }

        $samples = $this->samples($resource, $context);
        $request = Request::create('/');

        try {
            $plain = $this->instance($resource, $samples[0] ?? null)?->toArray($request) ?? [];
            $schema = $this->inferrer->infer($plain, $context);
        } catch (Throwable $error) {
            $context->diagnostics->warning(Stage::Response, "cannot run {$resource}::toArray(): {$error->getMessage()}");

            return Schema::object();
        }

        $this->remember($resource, $name, is_array($plain) ? $plain : [], $samples[1] ?? null, $context);

        if (! ($samples[0] ?? null) instanceof Model) {
            return $schema;
        }

        try {
            $loaded = $this->instance($resource, $this->relations->load($samples[0], $context))?->toArray($request) ?? [];

            return self::union($schema, $this->inferrer->infer($loaded, $context));
        } catch (Throwable $error) {
            $context->diagnostics->warning(Stage::Response, "cannot run {$resource}::toArray() with the relations of its model, described without them: {$error->getMessage()}");

            return $schema;
        }
    }

    /**
     * Keeps the scalar values of the first sample that the second sample gives too.
     *
     * @param class-string<JsonResource> $resource
     * @param array<array-key, mixed> $plain
     */
    private function remember(string $resource, string $name, array $plain, mixed $other, GenerationContext $context): void
    {
        try {
            $second = $this->instance($resource, $other)?->toArray(Request::create('/'));
        } catch (Throwable) {
            $second = null;
        }

        $values = [];

        foreach ($plain as $key => $value) {
            if (is_scalar($value) && (! is_array($second) || ($second[$key] ?? null) === $value)) {
                $values[(string) $key] = $value;
            }
        }

        $state = $this->state($context);
        $state['values'][$name] = $values;
        $this->states[$context] = $state;
    }

    /**
     * `with()` and `$additional` of a sampled instance.
     *
     * @param class-string<JsonResource> $resource
     *
     * @return array<string, Schema>
     */
    private function extras(string $resource, GenerationContext $context): array
    {
        $instance = $this->instance($resource, $this->samples($resource, $context)[0] ?? null);
        $extras = [];

        try {
            foreach ($instance === null ? [] : [...$instance->with(Request::create('/')), ...$instance->additional] as $key => $value) {
                $extras[(string) $key] = $this->inferrer->infer($value, $context);
            }
        } catch (Throwable) {
            // with() that fails adds nothing.
        }

        return $extras;
    }

    /**
     * Two samples of what the resource receives (its model, or what its #[ResourceFactory] builds), sampled once per
     * context; none when there is nothing to sample.
     *
     * @param class-string<JsonResource> $resource
     *
     * @return list<mixed>
     */
    public function samples(string $resource, GenerationContext $context): array
    {
        $state = $this->state($context);

        if (isset($state['samples'][$resource])) {
            return $state['samples'][$resource];
        }

        $model = $this->models->model($resource);
        $factory = (new ReflectionClass($resource))->getAttributes(ResourceFactory::class) !== [];
        $samples = $model === null && ! $factory ? [] : $this->samplers->sample(new SampleRequest($model ?? $resource, $resource, 2), $context) ?? [];
        $state['samples'][$resource] = $samples;
        $this->states[$context] = $state;

        return $samples;
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
     * The schema a reference points to.
     */
    private static function resolved(Schema $schema, GenerationContext $context): Schema
    {
        $ref = $schema->ref;

        return is_string($ref) ? $context->components->get(substr($ref, (int) strrpos($ref, '/') + 1)) ?? $schema : $schema;
    }

    /**
     * @return array{described: array<string, true>, values: array<string, array<string, mixed>>, samples: array<string, list<mixed>>}
     */
    private function state(GenerationContext $context): array
    {
        return $this->states[$context] ?? ['described' => [], 'values' => [], 'samples' => []];
    }
}
