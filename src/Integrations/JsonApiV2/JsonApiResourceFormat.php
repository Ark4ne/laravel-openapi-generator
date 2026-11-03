<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApiV2;

use Ark4ne\JsonApi\Definition\Cardinality;
use Ark4ne\JsonApi\Definition\RelationshipDefinition;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Extraction\Responses\Paginations;
use Ark4ne\OpenApi\Extraction\Responses\ResourceFormat;
use Ark4ne\OpenApi\Extraction\Responses\ResourceSchemas;
use Ark4ne\OpenApiWriter\Objects\Discriminator;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;
use WeakMap;

/**
 * JSON:API resources of `ark4ne/laravel-json-api` 2.x as resource objects, and their documents, read from the
 * definition the package compiles (`ResourceDefinition`): no reflection on the package, no instance but on a sample.
 *
 * - The component: `type` (the type of the definition), `id` (always a string), `attributes`, `relationships`,
 *   `links` and `meta`; only `type` and `id` are required, sparse fieldsets and conditions letting any other member
 *   out.
 * - Values: typed by their `ValueType` (AttributeSchemas); a `Mixed` value is inferred from the resource rendered on a
 *   sample of its model, through its public API (`toResponse()`), only when it has one.
 * - Relationships: `data`, a resource identifier (`type`, `id`), nullable for one, a list for many; the `type` of
 *   each related resource, an enum of them when polymorphic, that of a native resource when it can be read without
 *   model. `links` (their names when declared by name) and `meta` when declared; their content is not known.
 * - Requests: the resource object under `data`.
 * - Documents: `jsonapi` (as configured), `data`, `included` (every resource reachable through the relationships,
 *   `oneOf` with a discriminator on `type` unless two of them share a type or one is native), the `meta` of
 *   `toDocumentMeta()`; a paginated collection adds Laravel's `links` and `meta`.
 */
final class JsonApiResourceFormat implements ResourceFormat
{
    public const MEDIA_TYPE = 'application/vnd.api+json';

    private ResourceDefinitions $definitions;

    /** @var WeakMap<GenerationContext, array<string, array<array-key, mixed>>> resource → document rendered on a sample, per context */
    private WeakMap $rendered;

    /**
     * @param ResourceDefinitions $definitions shared with the rule mappers of the integration
     */
    public function __construct(ResourceDefinitions $definitions = new ResourceDefinitions())
    {
        $this->definitions = $definitions;
        $this->rendered = new WeakMap();
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
        $definition = is_subclass_of($resource, JsonApiResource::class) ? $this->definitions->of($resource, $context, Stage::Response, $resource) : null;
        $members = [
            'type' => $definition === null ? Schema::string() : Schema::string()->const($definition->type),
            'id' => Schema::string(),
        ];

        if ($definition !== null && is_subclass_of($resource, JsonApiResource::class)) {
            $values = $this->values($resource, $resources, $context);
            $members += [
                'attributes' => $values('data.attributes')->members($definition->attributes),
                'relationships' => $this->relationships($definition, $context),
                'links' => $values('data.links')->members($definition->links),
                'meta' => $values('data.meta')->members($definition->meta),
            ];
        }

        $properties = [];

        foreach ($members as $name => $member) {
            if ($member !== null) {
                $properties[] = $member->objectId($name);
            }
        }

        return Schema::object()->properties(...$properties)->required('type', 'id');
    }

    public function document(string $resource, Schema $data, ResourceSchemas $resources, GenerationContext $context): Schema
    {
        return $this->body($resource, ['data' => $data], ['data'], $resources, $context);
    }

    public function request(string $resource, Schema $data, ResourceSchemas $resources, GenerationContext $context): Schema
    {
        return Paginations::object(['data' => $data]);
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
     * A top-level document: `jsonapi`, its members, `included`, and the `meta` of `toDocumentMeta()` (joined with the
     * pagination's).
     *
     * @param class-string<JsonResource> $resource
     * @param array<string, Schema> $members
     * @param list<string> $required
     */
    private function body(string $resource, array $members, array $required, ResourceSchemas $resources, GenerationContext $context): Schema
    {
        $jsonapi = JsonApiObjects::schema(ResourceDefinitions::jsonApiObject(), $context);
        $definition = is_subclass_of($resource, JsonApiResource::class) ? $this->definitions->of($resource, $context, Stage::Response, $resource) : null;

        if ($jsonapi !== null) {
            [$members, $required] = [['jsonapi' => $jsonapi, ...$members], ['jsonapi', ...$required]];
        }

        if ($definition !== null && is_subclass_of($resource, JsonApiResource::class)) {
            $included = $this->included($resource, $definition, $resources, $context);
            $meta = $this->values($resource, $resources, $context)('meta')->members($definition->documentMeta);
            $members = $included === null ? $members : [...$members, 'included' => $included];

            if ($meta !== null) {
                $members['meta'] = isset($members['meta']) ? self::join($members['meta'], $meta) : $meta;
            }
        }

        $properties = [];

        foreach ($members as $name => $member) {
            $properties[] = $member->objectId($name);
        }

        return Schema::object()->properties(...$properties)->required(...$required);
    }

    /**
     * The members of `toDocumentMeta()` added to the pagination's `meta`, whose members stay required.
     */
    private static function join(Schema $pagination, Schema $meta): Schema
    {
        $properties = [...$pagination->properties ?? [], ...$meta->properties ?? []];
        $joined = $pagination->properties(...array_values($properties));

        return $pagination->required === null ? $joined : $joined->required(...$pagination->required);
    }

    /**
     * Every resource reachable through the relationships of the definitions, breadth first: one, or `oneOf` them with
     * a discriminator on `type`. A native resource is a leaf, written as a resource object of its type.
     *
     * @param class-string<JsonApiResource<mixed>> $resource
     */
    private function included(string $resource, ResourceDefinition $definition, ResourceSchemas $resources, GenerationContext $context): ?Schema
    {
        /** @var array<string, ResourceDefinition> $reached */
        $reached = [];
        $natives = [];
        $queue = [$definition];

        while (($current = array_shift($queue)) !== null) {
            foreach ($current->relationships as $relationship) {
                if ($relationship->native !== null) {
                    $natives[$relationship->native] = true;
                }

                foreach ($relationship->related as $related) {
                    $known = isset($reached[$related]) ? null : $this->definitions->of($related, $context, Stage::Response, $resource);

                    if ($known !== null) {
                        $reached[$related] = $known;
                        $queue[] = $known;
                    }
                }
            }
        }

        $members = [];
        $mapping = [];
        $typed = [];
        $discriminated = $context->document->useReferences && $natives === [];

        foreach ($reached as $related => $known) {
            if (! is_subclass_of($related, JsonResource::class)) {
                continue;
            }

            $members[] = $resources->schema($related, $context);

            if (isset($typed[$known->type])) {
                $context->diagnostics->warning(Stage::Response, "{$resource}: the included resources {$typed[$known->type]} and {$related} share the type {$known->type}: written without discriminator");
                $discriminated = false;
            }

            $typed[$known->type] = $related;
            $mapping[$known->type] = '#/components/schemas/' . $context->components->nameOf($related);
        }

        foreach (array_keys($natives) as $native) {
            $members[] = self::native($native);
        }

        if ($members === []) {
            return null;
        }

        if (count($members) === 1) {
            return Schema::array()->items($members[0]);
        }

        $items = Schema::create()->oneOf(...$members);

        return Schema::array()->items($discriminated ? $items->discriminator(Discriminator::create()->propertyName('type')->mapping($mapping)) : $items);
    }

    /**
     * A resource object of Laravel's native JSON:API: its type when it can be read, members of unknown content.
     *
     * @param class-string<\Illuminate\Http\Resources\JsonApi\JsonApiResource> $native
     */
    private static function native(string $native): Schema
    {
        $type = ResourceDefinitions::nativeType($native);

        return Schema::object()->properties(
            ($type === null ? Schema::string() : Schema::string()->const($type))->objectId('type'),
            Schema::string()->objectId('id'),
            Schema::object()->objectId('attributes'),
            Schema::object()->objectId('relationships'),
            Schema::object()->objectId('links'),
            Schema::object()->objectId('meta'),
        )->required('type', 'id');
    }

    private function relationships(ResourceDefinition $definition, GenerationContext $context): ?Schema
    {
        $properties = [];

        foreach ($definition->relationships as $name => $relationship) {
            $identifier = $this->identifier($relationship, $definition, $context);
            $members = [($relationship->cardinality === Cardinality::Many ? Schema::array()->items($identifier) : $identifier->nullable())->objectId('data')];

            if ($relationship->hasLinks) {
                $links = array_map(static fn(string $link): Schema => Schema::string()->objectId($link), $relationship->namedLinks);
                $members[] = ($links === [] ? Schema::object() : Schema::object()->properties(...$links))->objectId('links');
            }

            if ($relationship->hasMeta) {
                $members[] = Schema::object()->objectId('meta');
            }

            $properties[] = Schema::object()->properties(...$members)->objectId($name);
        }

        return $properties === [] ? null : Schema::object()->properties(...$properties);
    }

    /**
     * A resource identifier: the type of the related resource, an enum of them when polymorphic, a string when
     * unknown; its `meta` when the relationship declares identifier meta.
     */
    private function identifier(RelationshipDefinition $relationship, ResourceDefinition $definition, GenerationContext $context): Schema
    {
        $types = [];

        foreach ($relationship->related as $related) {
            $type = $this->definitions->of($related, $context, Stage::Response, $definition->class)?->type;

            if ($type !== null && ! in_array($type, $types, true)) {
                $types[] = $type;
            }
        }

        $native = $relationship->native === null ? null : ResourceDefinitions::nativeType($relationship->native);
        $types = $native === null ? $types : [$native];
        $type = match (count($types)) {
            0 => Schema::string(),
            1 => Schema::string()->const($types[0]),
            default => Schema::string()->enum(...$types),
        };
        $identifier = Paginations::object(['type' => $type, 'id' => Schema::string()]);

        return $relationship->hasIdentifierMeta ? $identifier->addProperties(Schema::object()->objectId('meta')) : $identifier;
    }

    /**
     * The schemas of the values of a member of the document rendered on a sample (`data.attributes`, `meta`…): the
     * resource is rendered for its first `Mixed` value only.
     *
     * @param class-string<JsonApiResource<mixed>> $resource
     *
     * @return Closure(string): AttributeSchemas
     */
    private function values(string $resource, ResourceSchemas $resources, GenerationContext $context): Closure
    {
        $model = $resources->models()->model($resource);

        return fn(string $member): AttributeSchemas => new AttributeSchemas($context, $model, function (string $path) use ($resource, $member, $resources, $context): Schema {
            $value = $this->rendered($resource, $resources, $context);

            foreach (explode('.', "{$member}.{$path}") as $segment) {
                if (! is_array($value) || ! array_key_exists($segment, $value)) {
                    return Schema::create();
                }

                $value = $value[$segment];
            }

            return $resources->inferrer()->infer($value, $context);
        });
    }

    /**
     * The document of the resource rendered on a sample of its model, once per context; empty without sample, or with
     * a warning when it fails.
     *
     * @param class-string<JsonApiResource<mixed>> $resource
     *
     * @return array<array-key, mixed>
     */
    private function rendered(string $resource, ResourceSchemas $resources, GenerationContext $context): array
    {
        $known = $this->rendered[$context] ?? [];

        if (isset($known[$resource])) {
            return $known[$resource];
        }

        $sample = $resources->samples($resource, $context)[0] ?? null;
        $document = [];

        if ($sample !== null) {
            try {
                $response = (new $resource(self::identified($sample)))->toResponse(Request::create('/'));
                $data = $response->getData(true);
                $document = is_array($data) ? $data : [];
            } catch (Throwable $error) {
                $context->diagnostics->warning(Stage::Response, "cannot render {$resource} on a sample: {$error->getMessage()}");
            }
        }

        $known = $this->rendered[$context] ?? [];
        $known[$resource] = $document;
        $this->rendered[$context] = $known;

        return $document;
    }

    /**
     * A model sample not saved (`make()`, without transaction) has no key, and the package renders no resource without
     * `id`: a copy of it gets a placeholder key.
     */
    private static function identified(mixed $sample): mixed
    {
        if (! $sample instanceof Model || $sample->getKey() !== null) {
            return $sample;
        }

        return (clone $sample)->setAttribute($sample->getKeyName(), $sample->getKeyType() === 'int' ? 1 : '1');
    }
}
