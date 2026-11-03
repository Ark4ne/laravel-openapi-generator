<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApiV2;

use Ark4ne\JsonApi\Definition\AttributeDefinition;
use Ark4ne\JsonApi\Definition\ValueType;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Support\DateFormats;
use Ark4ne\OpenApi\Support\Enums;
use Ark4ne\OpenApiWriter\Objects\Schema;
use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

/**
 * The schema of the values a definition declares (`AttributeDefinition`): attributes, links and meta.
 *
 * - The `ValueType` gives the type; a date its format (`date_formats`), an enum its cases (of the enum class, else of
 *   the cast of the model), an array its items, a struct its members.
 * - A `Mixed` value (a closure without descriptor, a raw value) is typed by the sample: `$mixed` gives the schema of
 *   the value rendered at a dotted path, an empty schema without one.
 * - A nullable value is nullable; `null` is then one of the values of an enum.
 */
final readonly class AttributeSchemas
{
    /**
     * @param class-string<Model>|null $model the model of the resource, whose casts give the enums of unknown class
     * @param Closure(string): Schema $mixed the schema of the value rendered at a dotted path
     */
    public function __construct(
        private GenerationContext $context,
        private ?string $model,
        private Closure $mixed,
    ) {}

    /**
     * An object of the values, none required (sparse fieldsets, conditions); `null` when there is none.
     *
     * @param array<string, AttributeDefinition> $definitions
     */
    public function members(array $definitions, string $prefix = ''): ?Schema
    {
        $properties = [];

        foreach ($definitions as $name => $definition) {
            $properties[] = $this->schema($definition, $prefix . $name)->objectId($name);
        }

        return $properties === [] ? null : Schema::object()->properties(...$properties);
    }

    /**
     * @param string $path the dotted path of the value in the rendered member
     */
    public function schema(AttributeDefinition $definition, string $path): Schema
    {
        $schema = match ($definition->type) {
            ValueType::String => $definition->format === 'uuid' ? Schema::string()->format('uuid') : Schema::string(),
            ValueType::Integer => Schema::integer(),
            ValueType::Number => Schema::number(),
            ValueType::Boolean => Schema::boolean(),
            ValueType::Date => $this->date($definition->format),
            ValueType::Enum => $this->enum($definition),
            ValueType::Array => Schema::array()->items($definition->items === null ? Schema::create() : $this->schema($definition->items, "{$path}.0")),
            ValueType::Struct => $this->members($definition->children, "{$path}.") ?? Schema::object(),
            ValueType::Mixed => ($this->mixed)($path),
        };

        if (! $definition->nullable || $definition->type === ValueType::Mixed || $schema->type === null) {
            return $schema;
        }

        // `null` is one of the values of a nullable enum, or the enum rejects it.
        return $schema->enum === null ? $schema->nullable() : $schema->nullable()->enum(...$schema->enum, ...[null]);
    }

    private function date(?string $format): Schema
    {
        $formats = $this->context->configuration->dateFormats === [] ? DateFormats::DEFAULTS : $this->context->configuration->dateFormats;
        $openapi = $format === null ? null : $formats[$format] ?? null;

        return $openapi === null ? Schema::string() : Schema::string()->format($openapi);
    }

    /**
     * The cases of the enum class of the definition, else of the cast of the attribute on the model.
     */
    private function enum(AttributeDefinition $definition): Schema
    {
        $enum = $definition->enum;

        if ($enum === null && $this->model !== null) {
            try {
                $cast = (new $this->model())->getCasts()[$definition->source ?? $definition->name] ?? null;
                $enum = is_string($cast) && enum_exists($cast) ? $cast : null;
            } catch (Throwable) {
                $enum = null;
            }
        }

        return match (true) {
            $enum !== null && is_subclass_of($enum, BackedEnum::class) => Schema::create()->type(Enums::type($enum))->enum(...Enums::values($enum)),
            $enum !== null => Schema::string()->enum(...array_map(static fn(UnitEnum $case): string => $case->name, $enum::cases())),
            default => Schema::create(),
        };
    }
}
