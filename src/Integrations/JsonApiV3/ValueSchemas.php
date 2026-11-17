<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApiV3;

use Ark4ne\ApiRevisions\Compilation\FieldDefinition;
use Ark4ne\ApiRevisions\Compilation\Shape;
use Ark4ne\ApiRevisions\Definition\Kind;
use Ark4ne\ApiRevisions\Definition\TypeDefinition;
use Ark4ne\OpenApi\Context\Diagnostic;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Support\DateFormats;
use Ark4ne\OpenApi\Support\Patterns;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Closure;

/**
 * The schema of the values a definition declares, the shapes of `ark4ne/laravel-api-revisions` at the revision of the
 * definition (`Shape`, `FieldDefinition`, `TypeDefinition`): attributes, resource meta and document meta. The keywords
 * follow the JSON Schema export of the base (`JsonSchemaExporter`).
 *
 * - Each field under its name at the revision; none required (sparse fieldsets, conditions).
 * - The `Kind` gives the type: `String` (`minLength`, `maxLength`, `format`, `pattern`), `Int` and `Float` (`minimum`,
 *   `maximum`), `Bool`, `Date` and `DateTime` (the OpenAPI format of their PHP format in `date_formats`, a plain string
 *   for another one), `Enum` (its backed values), `List` (`minItems`, `maxItems`, its items), `Object` (its children,
 *   for an object typed by the schema of a `was()`, its member types).
 * - `Any`, `Custom` and a nested resource are typed by the value rendered at their path on a sample (`$sampled`): the
 *   JSON Schema fragment of a custom type is not read, the shape of a nested resource is not given by the definition
 *   (an info diagnostic, once per type name or class).
 * - The documentation of the type: `description`, its first example (when the examples are on), `deprecated`.
 * - A nullable value is nullable; `null` is then one of the values of an enum.
 */
final readonly class ValueSchemas
{
    /**
     * @param Closure(string): Schema $sampled the schema of the value rendered at a dotted path
     */
    public function __construct(
        private GenerationContext $context,
        private Closure $sampled,
    ) {}

    /**
     * An object of the fields of a shape, none required; `null` when there is no shape or no field.
     *
     * @param string $prefix the dotted path of the object in the rendered member, `''` at its top
     */
    public function members(?Shape $shape, string $prefix = ''): ?Schema
    {
        $properties = [];

        foreach ($shape->fields ?? [] as $field) {
            $properties[] = $this->field($field, $prefix . $field->name)->objectId($field->name);
        }

        return $properties === [] ? null : Schema::object()->properties(...$properties);
    }

    /**
     * The schema of one field at the revision of its shape: its children and items read from the field where the head
     * is in force, from its type where the schema of a `was()` is (as the base exports it).
     *
     * @param string $path the dotted path of the value in the rendered member
     */
    public function field(FieldDefinition $field, string $path): Schema
    {
        $head = $field->schemaChange === null;

        return $this->finish($this->type($field->type, $path, $head ? $field->children : null, $head ? $field->items : null), $field->type, $field->nullable);
    }

    /**
     * The schema its kind and its constraints give.
     */
    private function type(TypeDefinition $type, string $path, ?Shape $children = null, ?FieldDefinition $items = null): Schema
    {
        return match ($type->kind) {
            Kind::String => $this->string($type),
            Kind::Int => self::bounded(Schema::integer(), $type),
            Kind::Float => self::bounded(Schema::number(), $type),
            Kind::Bool => Schema::boolean(),
            Kind::Date, Kind::DateTime => $this->date($type->format),
            Kind::Enum => Schema::create()->type($type->backing === Kind::Int ? 'integer' : 'string')->enum(...$type->values),
            Kind::List => $this->list($type, $path, $items),
            Kind::Object => $this->object($type, $path, $children),
            Kind::Custom => $this->custom($type, $path),
            Kind::Any => ($this->sampled)($path),
        };
    }

    private function string(TypeDefinition $type): Schema
    {
        $schema = Schema::string();
        $schema = $type->min === null ? $schema : $schema->minLength((int) $type->min);
        $schema = $type->max === null ? $schema : $schema->maxLength((int) $type->max);
        $schema = $type->format === null ? $schema : $schema->format($type->format);
        $pattern = $type->pattern === null ? null : Patterns::ecma($type->pattern);

        return $pattern === null ? $schema : $schema->pattern($pattern);
    }

    private static function bounded(Schema $schema, TypeDefinition $type): Schema
    {
        $schema = $type->min === null ? $schema : $schema->minimum($type->min);

        return $type->max === null ? $schema : $schema->maximum($type->max);
    }

    /**
     * A string in the OpenAPI format of its PHP format (`date_formats`, its defaults without one), a plain string for
     * a format it does not list.
     */
    private function date(?string $format): Schema
    {
        $formats = $this->context->configuration->dateFormats === [] ? DateFormats::DEFAULTS : $this->context->configuration->dateFormats;
        $openapi = $format === null ? null : $formats[$format] ?? null;

        return $openapi === null ? Schema::string() : Schema::string()->format($openapi);
    }

    private function list(TypeDefinition $type, string $path, ?FieldDefinition $items): Schema
    {
        $schema = Schema::array();
        $schema = $type->min === null ? $schema : $schema->minItems((int) $type->min);
        $schema = $type->max === null ? $schema : $schema->maxItems((int) $type->max);

        return match (true) {
            $items !== null => $schema->items($this->field($items, "{$path}.0")),
            $type->items !== null => $schema->items($this->finish($this->type($type->items, "{$path}.0"), $type->items, $type->items->nullable)),
            default => $schema->items(Schema::create()),
        };
    }

    /**
     * Its children (the fields of the head, else the types of the schema of a `was()`); a nested resource typed by the
     * sample, its shape not being given by the definition (reported once per class).
     */
    private function object(TypeDefinition $type, string $path, ?Shape $children): Schema
    {
        if ($type->resource !== null) {
            $this->once("the nested resource {$type->resource} is typed from a sample: its shape is not given by the definition");

            return ($this->sampled)($path);
        }

        if ($children !== null) {
            return $this->members($children, "{$path}.") ?? Schema::object();
        }

        $properties = [];

        foreach ($type->children as $name => $child) {
            $properties[] = $this->finish($this->type($child, "{$path}.{$name}"), $child, $child->nullable)->objectId($name);
        }

        return $properties === [] ? Schema::object() : Schema::object()->properties(...$properties);
    }

    /**
     * Typed by the sample: the generator reads no JSON Schema (its fragment), reported once per type name.
     */
    private function custom(TypeDefinition $type, string $path): Schema
    {
        $this->once("the custom type {$type->name} is typed from a sample: its JSON Schema is not read");

        return ($this->sampled)($path);
    }

    /**
     * An info diagnostic, unless the context already has it.
     */
    private function once(string $message): void
    {
        if (! in_array($message, array_map(static fn(Diagnostic $diagnostic): string => $diagnostic->message, $this->context->diagnostics->all()), true)) {
            $this->context->diagnostics->info(Stage::Response, $message);
        }
    }

    /**
     * Its documentation, then `null` accepted when nullable (`null` one of the values of an enum); a value typed by
     * the sample is left as the sample gives it.
     */
    private function finish(Schema $schema, TypeDefinition $type, bool $nullable): Schema
    {
        $schema = $type->description === null ? $schema : $schema->description($type->description);
        $schema = $type->examples === [] || ! $this->context->configuration->sampling->examples ? $schema : $schema->example($type->examples[0]);
        $schema = $type->deprecated ? $schema->deprecated() : $schema;

        if (! $nullable || $type->kind === Kind::Any || $type->kind === Kind::Custom || $type->resource !== null || $schema->type === null) {
            return $schema;
        }

        return $schema->enum === null ? $schema->nullable() : $schema->nullable()->enum(...$schema->enum, ...[null]);
    }
}
