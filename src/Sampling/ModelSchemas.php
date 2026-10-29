<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Support\Enums;
use Ark4ne\OpenApi\Types\NullableType;
use Ark4ne\OpenApi\Types\TypeResolver;
use Ark4ne\OpenApiWriter\Objects\Schema;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use Throwable;

/**
 * The declared schemas of the attributes of a model: its `@property`, then its casts (which win on the type), then
 * the nullability of its columns when a connection answers (`Schema::getColumns()`).
 *
 * Casts whose JSON depends on the value (`array`, `json`, `object`, custom casts) declare nothing: the value is read.
 */
final class ModelSchemas
{
    /** @var array<string, array<string, bool>> table → column → nullable */
    private array $columns = [];

    public function __construct(
        private readonly TypeResolver $types,
    ) {}

    /**
     * @return array<string, Schema> attribute → schema, `@property` first, then the other casts
     */
    public function of(Model $model): array
    {
        $schemas = [];
        $nullable = [];

        foreach ($this->types->properties(new ReflectionClass($model)) as $name => $type) {
            $schemas[$name] = TypeSchemas::of($type instanceof NullableType ? $type->type : $type);
            $nullable[$name] = $type instanceof NullableType;
        }

        foreach ($model->getCasts() as $name => $cast) {
            $schema = self::cast($cast);

            if ($schema !== null) {
                $schemas[$name] = $schema;
            }
        }

        $columns = $this->columns($model);

        foreach ($schemas as $name => $schema) {
            if (($nullable[$name] ?? false) || ($columns[$name] ?? false)) {
                $schemas[$name] = $schema->nullable();
            }
        }

        return $schemas;
    }

    private static function cast(string $cast): ?Schema
    {
        $parts = explode(':', $cast, 2);
        $name = $parts[0];
        $argument = $parts[1] ?? null;
        $lower = strtolower($name);

        return match (true) {
            in_array($lower, ['int', 'integer', 'timestamp'], true) => Schema::integer(),
            in_array($lower, ['real', 'float', 'double'], true) => Schema::number(),
            $lower === 'decimal' => Schema::string()->pattern('^-?[0-9]+\.[0-9]{' . (int) $argument . '}$'),
            in_array($lower, ['bool', 'boolean'], true) => Schema::boolean(),
            in_array($lower, ['string', 'hashed', 'encrypted'], true) => Schema::string(),
            in_array($lower, ['date', 'immutable_date'], true) => Schema::string()->format('date'),
            in_array($lower, ['datetime', 'immutable_datetime', 'custom_datetime', 'immutable_custom_datetime'], true) => Schema::string()->format('date-time'),
            in_array($lower, ['collection', 'illuminate\database\eloquent\casts\ascollection', 'illuminate\database\eloquent\casts\asarrayobject'], true) => Schema::array(),
            $lower === 'illuminate\database\eloquent\casts\asenumcollection' && is_string($argument) && is_subclass_of($argument, BackedEnum::class) => Schema::array()->items(Schema::create()->type(Enums::type($argument))->enum(...Enums::values($argument))),
            enum_exists($name) && is_subclass_of($name, BackedEnum::class) => Schema::create()->type(Enums::type($name))->enum(...Enums::values($name)),
            default => null,
        };
    }

    /**
     * @return array<string, bool> column → nullable; empty when no connection answers
     */
    private function columns(Model $model): array
    {
        $table = $model->getTable();

        if (isset($this->columns[$table])) {
            return $this->columns[$table];
        }

        $columns = [];

        try {
            foreach ($model->getConnection()->getSchemaBuilder()->getColumns($table) as $column) {
                $columns[$column['name']] = $column['nullable'];
            }
        } catch (Throwable) {
            // No connection: the declared types alone.
        }

        return $this->columns[$table] = $columns;
    }
}
