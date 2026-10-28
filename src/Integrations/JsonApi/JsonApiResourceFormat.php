<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApi;

use Ark4ne\JsonApi\Descriptors\Describer;
use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Descriptors\Relations\RelationMany;
use Ark4ne\JsonApi\Descriptors\Values\Value;
use Ark4ne\JsonApi\Descriptors\Values\ValueArray;
use Ark4ne\JsonApi\Descriptors\Values\ValueBool;
use Ark4ne\JsonApi\Descriptors\Values\ValueDate;
use Ark4ne\JsonApi\Descriptors\Values\ValueEnum;
use Ark4ne\JsonApi\Descriptors\Values\ValueFloat;
use Ark4ne\JsonApi\Descriptors\Values\ValueInteger;
use Ark4ne\JsonApi\Descriptors\Values\ValueString;
use Ark4ne\JsonApi\Descriptors\Values\ValueStruct;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\JsonApi\Resources\Relationship;
use Ark4ne\JsonApi\Support\FakeModel;
use Ark4ne\JsonApi\Support\Values;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Extraction\Responses\Paginations;
use Ark4ne\OpenApi\Extraction\Responses\ResourceFormat;
use Ark4ne\OpenApi\Extraction\Responses\ResourceSchemas;
use Ark4ne\OpenApi\Support\DateFormats;
use Ark4ne\OpenApi\Support\Enums;
use Ark4ne\OpenApiWriter\Objects\Discriminator;
use Ark4ne\OpenApiWriter\Objects\Schema;
use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;
use UnitEnum;
use WeakMap;

/**
 * JSON:API resources (`JsonApiResource`) as resource objects, and their documents.
 *
 * - The component: `id` (typed from a sample, `string` without one), `type` (the constant of `toType()`),
 *   `attributes`, `relationships`, `links` and `meta`; only `id` and `type` are required, sparse fieldsets and
 *   conditions letting any other member out.
 * - Values: a descriptor of the package (`$this->string()`, `$this->date()`, `$this->struct()`…) gives its type before
 *   any sampling; any other value is inferred from a sample of the model.
 * - Relationships: `data`, a resource identifier (`type`, `id`), nullable for one, a list for many; their `links` and
 *   `meta` from the closures of the relation.
 * - Documents: `data`, `included` (every resource reachable through the relationships, `oneOf` with a discriminator
 *   on `type`), the `meta` of `toMeta()`; a paginated collection adds Laravel's `links` and `meta`.
 */
final class JsonApiResourceFormat implements ResourceFormat
{
    public const MEDIA_TYPE = 'application/vnd.api+json';

    /** @var WeakMap<GenerationContext, array<string, string>> resource → type, per context */
    private WeakMap $types;

    /** @var WeakMap<GenerationContext, array<string, Schema>> resource → schema of its `id`, per context */
    private WeakMap $ids;

    /** @var WeakMap<GenerationContext, array<string, list<JsonApiRelation>>> resource → relations, per context */
    private WeakMap $relations;

    public function __construct()
    {
        $this->types = new WeakMap();
        $this->ids = new WeakMap();
        $this->relations = new WeakMap();
    }

    public function supports(string $resource): bool
    {
        return is_subclass_of($resource, JsonApiResource::class);
    }

    public function mediaType(): string
    {
        return self::MEDIA_TYPE;
    }

    public function component(string $resource, ResourceSchemas $resources, GenerationContext $context): Schema
    {
        if (! is_subclass_of($resource, JsonApiResource::class)) {
            return Schema::object();
        }

        $sampled = $this->sampled($resource, $resources, $context);
        $model = $resources->models()->model($resource);
        $members = [
            'id' => $this->identifier($resource, $resources, $context),
            'type' => Schema::string()->const($this->type($resource, $context)),
            'attributes' => $this->members($sampled, 'toAttributes', $model, $resources, $context),
            'relationships' => $this->relationships($resource, $resources, $context),
            'links' => $this->members($sampled, 'toLinks', $model, $resources, $context),
            'meta' => $this->members($sampled, 'toResourceMeta', $model, $resources, $context),
        ];
        $properties = [];

        foreach ($members as $name => $member) {
            if ($member !== null) {
                $properties[] = $member->objectId($name);
            }
        }

        return Schema::object()->properties(...$properties)->required('id', 'type');
    }

    public function document(string $resource, Schema $data, ResourceSchemas $resources, GenerationContext $context): Schema
    {
        return $this->body($resource, ['data' => $data], ['data'], $resources, $context);
    }

    public function collection(string $resource, Schema $data, ?string $paginator, ResourceSchemas $resources, GenerationContext $context): Schema
    {
        if ($paginator === null) {
            return $this->body($resource, ['data' => Schema::array()->items($data)], ['data'], $resources, $context);
        }

        $paginated = Paginations::resource($paginator, $data);
        $members = array_filter($paginated->properties ?? [], static fn(mixed $member): bool => $member instanceof Schema);

        return $this->body($resource, $members, $paginated->required ?? [], $resources, $context);
    }

    /**
     * A top-level document: its members, `included`, and the `meta` of `toMeta()` (joined with the pagination's).
     *
     * @param class-string<\Illuminate\Http\Resources\Json\JsonResource> $resource
     * @param array<string, Schema> $members
     * @param list<string> $required
     */
    private function body(string $resource, array $members, array $required, ResourceSchemas $resources, GenerationContext $context): Schema
    {
        if (is_subclass_of($resource, JsonApiResource::class)) {
            $included = $this->included($resource, $resources, $context);
            $meta = $this->members($this->sampled($resource, $resources, $context), 'toMeta', $resources->models()->model($resource), $resources, $context);
            $members = $included === null ? $members : [...$members, 'included' => $included];

            if ($meta !== null) {
                $members['meta'] = isset($members['meta']) ? ResourceSchemas::union($members['meta'], $meta) : $meta;
            }
        }

        $properties = [];

        foreach ($members as $name => $member) {
            $properties[] = $member->objectId($name);
        }

        return Schema::object()->properties(...$properties)->required(...$required);
    }

    /**
     * Every resource reachable through the relationships, breadth first: one, or `oneOf` them with a discriminator
     * on `type`.
     *
     * @param class-string<JsonApiResource<mixed>> $resource
     */
    private function included(string $resource, ResourceSchemas $resources, GenerationContext $context): ?Schema
    {
        $reached = [];
        $queue = [$resource];

        while ($queue !== []) {
            foreach ($this->relations(array_shift($queue), $context) as $relation) {
                if (! isset($reached[$relation->related])) {
                    $reached[$relation->related] = true;
                    $queue[] = $relation->related;
                }
            }
        }

        $members = [];
        $mapping = [];

        foreach (array_keys($reached) as $related) {
            $members[] = $resources->schema($related, $context);
            $mapping[$this->type($related, $context)] = '#/components/schemas/' . $context->components->nameOf($related);
        }

        if ($members === []) {
            return null;
        }

        if (count($members) === 1) {
            return Schema::array()->items($members[0]);
        }

        $items = Schema::create()->oneOf(...$members);

        return Schema::array()->items($context->document->useReferences ? $items->discriminator(Discriminator::create()->propertyName('type')->mapping($mapping)) : $items);
    }

    /**
     * @param class-string<JsonApiResource<mixed>> $resource
     */
    private function relationships(string $resource, ResourceSchemas $resources, GenerationContext $context): ?Schema
    {
        $properties = [];

        foreach ($this->relations($resource, $context) as $relation) {
            $identifier = Paginations::object([
                'type' => Schema::string()->const($this->type($relation->related, $context)),
                'id' => $this->identifier($relation->related, $resources, $context),
            ]);
            $members = [($relation->many ? Schema::array()->items($identifier) : $identifier->nullable())->objectId('data')];

            foreach (['links' => $relation->links, 'meta' => $relation->meta] as $member => $closure) {
                $value = $closure === null ? null : $this->call($resource, "{$relation->name}.{$member}", static fn(): mixed => $closure(self::instance($relation->related, new FakeModel())), $context);

                if (is_array($value) && $value !== []) {
                    $members[] = $resources->inferrer()->infer($value, $context)->objectId($member);
                }
            }

            $properties[] = Schema::object()->properties(...$members)->objectId($relation->name);
        }

        return $properties === [] ? null : Schema::object()->properties(...$properties);
    }

    /**
     * The relations of a resource, read once per context on an instance of the package's fake model.
     *
     * @param class-string<JsonApiResource<mixed>> $resource
     *
     * @return list<JsonApiRelation>
     */
    private function relations(string $resource, GenerationContext $context): array
    {
        $known = $this->relations[$context] ?? [];

        if (isset($known[$resource])) {
            return $known[$resource];
        }

        $instance = self::instance($resource, new FakeModel());
        $declared = $this->call($resource, 'toRelationships', static fn(): mixed => (new ReflectionMethod($instance, 'toRelationships'))->invoke($instance, Request::create('/')), $context);
        $relations = [];

        foreach (Values::mergeValues(self::keyed($declared)) as $key => $relation) {
            if (! $relation instanceof Relation && ! $relation instanceof Relationship) {
                continue;
            }

            [$related, $many] = $relation instanceof Relation
                ? [$relation->related(), $relation instanceof RelationMany]
                : [$relation->getResource(), self::read($relation, 'asCollection') === true];

            if (is_subclass_of($related, ResourceCollection::class)) {
                [$related, $many] = [self::read((new ReflectionClass($related))->newInstanceWithoutConstructor(), 'collects'), true];
            }

            if (! is_string($related) || ! is_subclass_of($related, JsonApiResource::class)) {
                continue;
            }

            $links = self::read($relation, 'links');
            $meta = self::read($relation, 'meta');
            $relations[] = new JsonApiRelation((string) Describer::retrieveName($relation, $key), $related, $many, $links instanceof Closure ? $links : null, $meta instanceof Closure ? $meta : null);
        }

        $known = $this->relations[$context] ?? [];
        $known[$resource] = $relations;
        $this->relations[$context] = $known;

        return $relations;
    }

    /**
     * The members a method returns (`toAttributes`, `toLinks`, `toResourceMeta`, `toMeta`), `null` when it has none.
     *
     * @param class-string<Model>|null $model
     * @param JsonApiResource<mixed> $instance
     */
    private function members(JsonApiResource $instance, string $method, ?string $model, ResourceSchemas $resources, GenerationContext $context): ?Schema
    {
        $values = $this->call($instance::class, $method, static fn(): mixed => (new ReflectionMethod($instance, $method))->invoke($instance, Request::create('/')), $context);

        return is_iterable($values) ? $this->values(self::keyed($values), $instance, $model, $resources, $context) : null;
    }

    /**
     * @param array<array-key, mixed> $values
     * @param class-string<Model>|null $model
     * @param JsonApiResource<mixed> $instance
     */
    private function values(array $values, JsonApiResource $instance, ?string $model, ResourceSchemas $resources, GenerationContext $context): ?Schema
    {
        $properties = [];

        foreach (Values::mergeValues($values) as $key => $value) {
            if (! Values::isMissing($value)) {
                $name = (string) Describer::retrieveName($value, $key);
                $properties[] = $this->value($value, $name, $instance, $model, $resources, $context)->objectId($name);
            }
        }

        return $properties === [] ? null : Schema::object()->properties(...$properties);
    }

    /**
     * @param class-string<Model>|null $model
     * @param JsonApiResource<mixed> $instance
     */
    private function value(mixed $value, string $name, JsonApiResource $instance, ?string $model, ResourceSchemas $resources, GenerationContext $context): Schema
    {
        if ($value instanceof Value) {
            return $this->descriptor($value, $name, $instance, $model, $resources, $context);
        }

        if ($value instanceof Closure) {
            try {
                $value = $value();
            } catch (Throwable) {
                return Schema::create();
            }
        }

        return $value instanceof FakeModel ? Schema::create() : $resources->inferrer()->infer($value, $context);
    }

    /**
     * The schema a descriptor of the package gives, nullable as the descriptor is.
     *
     * @param Value<mixed> $value
     * @param class-string<Model>|null $model
     * @param JsonApiResource<mixed> $instance
     */
    private function descriptor(Value $value, string $name, JsonApiResource $instance, ?string $model, ResourceSchemas $resources, GenerationContext $context): Schema
    {
        $schema = match (true) {
            $value instanceof ValueBool => Schema::boolean(),
            $value instanceof ValueInteger => Schema::integer(),
            $value instanceof ValueFloat => Schema::number(),
            $value instanceof ValueString => Schema::string(),
            $value instanceof ValueDate => self::date($value, $context),
            $value instanceof ValueArray => Schema::array()->items(self::item($value, $name, $instance, $model, $resources, $context, $this)),
            $value instanceof ValueEnum => self::enum($value, $name, $model),
            $value instanceof ValueStruct => $this->struct($value, $name, $instance, $model, $resources, $context),
            default => Schema::create(),
        };

        if (! $value->isNullable() || $schema->type === null) {
            return $schema;
        }

        // `null` is one of the values of a nullable enum, or the enum rejects it.
        return $schema->enum === null ? $schema->nullable() : $schema->nullable()->enum(...$schema->enum, ...[null]);
    }

    /**
     * @param ValueArray<mixed, mixed> $value
     * @param class-string<Model>|null $model
     * @param JsonApiResource<mixed> $instance
     */
    private static function item(ValueArray $value, string $name, JsonApiResource $instance, ?string $model, ResourceSchemas $resources, GenerationContext $context, self $format): Schema
    {
        $type = self::read($value, 'type');
        $type = is_string($type) && is_subclass_of($type, Value::class) ? new $type(null) : $type;

        return $type instanceof Value ? $format->descriptor($type, $name, $instance, $model, $resources, $context) : Schema::create();
    }

    /**
     * @param ValueStruct<mixed> $value
     * @param class-string<Model>|null $model
     * @param JsonApiResource<mixed> $instance
     */
    private function struct(ValueStruct $value, string $name, JsonApiResource $instance, ?string $model, ResourceSchemas $resources, GenerationContext $context): Schema
    {
        $retriever = $value->retriever();
        $values = $retriever instanceof Closure ? $this->call($instance::class, "{$name} struct", static fn(): mixed => $retriever($instance, $name), $context) : null;

        return (is_iterable($values) ? $this->values(self::keyed($values), $instance, $model, $resources, $context) : null) ?? Schema::object();
    }

    /**
     * @param ValueDate<mixed> $value
     */
    private static function date(ValueDate $value, GenerationContext $context): Schema
    {
        $formats = $context->configuration->dateFormats === [] ? DateFormats::DEFAULTS : $context->configuration->dateFormats;
        $format = self::read($value, 'format');
        $openapi = is_string($format) ? $formats[$format] ?? null : null;

        return $openapi === null ? Schema::string() : Schema::string()->format($openapi);
    }

    /**
     * The enum of the descriptor (`->of(Status::class)`), else of the cast of the attribute on the model.
     *
     * @param ValueEnum<mixed, UnitEnum> $value
     * @param class-string<Model>|null $model
     */
    private static function enum(ValueEnum $value, string $name, ?string $model): Schema
    {
        $enum = self::read($value, 'type');

        if (! is_string($enum) && $model !== null) {
            try {
                $enum = (new $model())->getCasts()[$name] ?? null;
            } catch (Throwable) {
                $enum = null;
            }
        }

        return match (true) {
            is_string($enum) && is_subclass_of($enum, BackedEnum::class) => Schema::create()->type(Enums::type($enum))->enum(...Enums::values($enum)),
            is_string($enum) && enum_exists($enum) => Schema::string()->enum(...array_map(static fn(UnitEnum $case): string => $case->name, $enum::cases())),
            default => Schema::create(),
        };
    }

    /**
     * The `id` of a resource: the type of `toIdentifier()` on a sample, of the key of a sampled model not saved,
     * `string` without sample.
     *
     * @param class-string<JsonApiResource<mixed>> $resource
     */
    private function identifier(string $resource, ResourceSchemas $resources, GenerationContext $context): Schema
    {
        $known = $this->ids[$context] ?? [];

        if (isset($known[$resource])) {
            return $known[$resource];
        }

        $sample = $resources->samples($resource, $context)[0] ?? null;
        $instance = $sample === null ? null : self::instance($resource, $sample);
        $id = $instance === null ? null : $this->call($resource, 'toIdentifier', static fn(): mixed => (new ReflectionMethod($instance, 'toIdentifier'))->invoke($instance, Request::create('/')), $context);
        $known = $this->ids[$context] ?? [];
        $known[$resource] = match (true) {
            is_int($id) => Schema::integer(),
            $id === null && $sample instanceof Model => $sample->getKeyType() === 'int' ? Schema::integer() : Schema::string(),
            default => Schema::string(),
        };
        $this->ids[$context] = $known;

        return $known[$resource];
    }

    /**
     * The `type` of a resource (`toType()`), the package's default when it fails.
     *
     * @param class-string<JsonApiResource<mixed>> $resource
     */
    private function type(string $resource, GenerationContext $context): string
    {
        $known = $this->types[$context] ?? [];

        if (isset($known[$resource])) {
            return $known[$resource];
        }

        $instance = self::instance($resource, new FakeModel());
        $type = $this->call($resource, 'toType', static fn(): mixed => (new ReflectionMethod($instance, 'toType'))->invoke($instance, Request::create('/')), $context);
        $known = $this->types[$context] ?? [];
        $known[$resource] = is_string($type) ? $type : Str::kebab(Str::beforeLast(class_basename($resource), 'Resource'));
        $this->types[$context] = $known;

        return $known[$resource];
    }

    /**
     * An instance on a sample of its model, on the package's fake model without one.
     *
     * @param class-string<JsonApiResource<mixed>> $resource
     *
     * @return JsonApiResource<mixed>
     */
    private function sampled(string $resource, ResourceSchemas $resources, GenerationContext $context): JsonApiResource
    {
        return self::instance($resource, $resources->samples($resource, $context)[0] ?? new FakeModel());
    }

    /**
     * Runs application code, `null` with a warning when it fails.
     *
     * @param Closure(): mixed $run
     */
    private function call(string $resource, string $method, Closure $run, GenerationContext $context): mixed
    {
        try {
            return $run();
        } catch (Throwable $error) {
            $context->diagnostics->warning(Stage::Response, "cannot run {$resource}::{$method}(): {$error->getMessage()}");

            return null;
        }
    }

    /**
     * @param class-string<JsonApiResource<mixed>> $resource
     *
     * @return JsonApiResource<mixed>
     */
    private static function instance(string $resource, mixed $sample): JsonApiResource
    {
        return new $resource($sample);
    }

    /**
     * The entries of what the application returned, those with an array key.
     *
     * @return array<array-key, mixed>
     */
    private static function keyed(mixed $values): array
    {
        $keyed = [];

        foreach (is_iterable($values) ? $values : [] as $key => $value) {
            if (is_int($key) || is_string($key)) {
                $keyed[$key] = $value;
            }
        }

        return $keyed;
    }

    /**
     * A protected property of the package's objects (the descriptors keep their settings there).
     */
    private static function read(object $object, string $property): mixed
    {
        return property_exists($object, $property) ? (new ReflectionProperty($object, $property))->getValue($object) : null;
    }
}
