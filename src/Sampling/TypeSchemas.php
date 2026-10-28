<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Support\Enums;
use Ark4ne\OpenApi\Types\ArrayShapeItem;
use Ark4ne\OpenApi\Types\ArrayShapeType;
use Ark4ne\OpenApi\Types\GenericType;
use Ark4ne\OpenApi\Types\IntersectionType;
use Ark4ne\OpenApi\Types\ListType;
use Ark4ne\OpenApi\Types\LiteralType;
use Ark4ne\OpenApi\Types\NamedType;
use Ark4ne\OpenApi\Types\NullableType;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApi\Types\UnionType;
use Ark4ne\OpenApiWriter\Objects\Schema;
use BackedEnum;
use DateTimeInterface;
use Traversable;

/**
 * The schema of a declared PHP type, without any sample: scalars, literals, lists, maps, shapes, enums, dates.
 *
 * A class that is neither an enum, a date nor a collection is an object; resources are described by sampling (E6).
 */
final class TypeSchemas
{
    public static function of(Type $type): Schema
    {
        return match (true) {
            $type instanceof NullableType => self::of($type->type)->nullable(),
            $type instanceof NamedType => self::named($type->name),
            $type instanceof LiteralType => self::literal($type->value)->const($type->value),
            $type instanceof ListType => Schema::array()->items(self::of($type->value))->minItems($type->nonEmpty ? 1 : null),
            $type instanceof GenericType => self::generic($type),
            $type instanceof ArrayShapeType => self::shape($type),
            $type instanceof UnionType => self::union($type),
            $type instanceof IntersectionType => Schema::create()->allOf(...array_map(self::of(...), $type->types)),
            default => Schema::create(),
        };
    }

    private static function named(string $name): Schema
    {
        return match ($name) {
            'int' => Schema::integer(),
            'positive-int' => Schema::integer()->minimum(1),
            'negative-int' => Schema::integer()->maximum(-1),
            'non-negative-int' => Schema::integer()->minimum(0),
            'non-positive-int' => Schema::integer()->maximum(0),
            'float', 'numeric' => Schema::number(),
            'string', 'literal-string', 'lowercase-string', 'uppercase-string', 'class-string', 'callable-string' => Schema::string(),
            'non-empty-string', 'non-falsy-string', 'truthy-string' => Schema::string()->minLength(1),
            'numeric-string' => Schema::string()->pattern('^-?[0-9]+(\.[0-9]+)?$'),
            'bool' => Schema::boolean(),
            'true' => Schema::boolean()->const(true),
            'false' => Schema::boolean()->const(false),
            'null', 'void' => Schema::null(),
            'array', 'iterable', 'non-empty-array' => Schema::array(),
            'object' => Schema::object(),
            'array-key' => Schema::create()->anyOf(Schema::string(), Schema::integer()),
            default => self::class($name),
        };
    }

    private static function class(string $class): Schema
    {
        return match (true) {
            enum_exists($class) && is_subclass_of($class, BackedEnum::class) => self::enum($class),
            is_a($class, DateTimeInterface::class, true) => Schema::string()->format('date-time'),
            in_array($class, NamedType::BUILTIN, true) => Schema::create(),
            default => Schema::object(),
        };
    }

    /**
     * @param class-string<BackedEnum> $enum
     */
    private static function enum(string $enum): Schema
    {
        return Schema::create()->type(Enums::type($enum))->enum(...Enums::values($enum));
    }

    private static function generic(GenericType $type): Schema
    {
        $arguments = $type->arguments;
        $last = $arguments[array_key_last($arguments)];

        if ($type->name === 'int') {
            return Schema::integer()->minimum(self::bound($arguments[0]))->maximum(self::bound($arguments[1] ?? new NamedType('max')));
        }

        if (in_array($type->name, ['array', 'iterable', 'non-empty-array'], true)) {
            $key = count($arguments) > 1 ? $arguments[0] : null;

            return $key instanceof NamedType && in_array($key->name, ['string', 'class-string', 'non-empty-string'], true)
                ? Schema::object()->additionalProperties(self::of($last))
                : Schema::array()->items(self::of($last));
        }

        if (is_a($type->name, Traversable::class, true)) {
            return Schema::array()->items(self::of($last));
        }

        return self::named($type->name);
    }

    private static function shape(ArrayShapeType $shape): Schema
    {
        if ($shape->list) {
            $count = count($shape->items);

            return Schema::array()
                ->prefixItems(...array_map(static fn(ArrayShapeItem $item): Schema => self::of($item->type), $shape->items))
                ->minItems($count)
                ->maxItems($shape->sealed ? $count : null);
        }

        $schema = Schema::object();
        $required = [];

        foreach ($shape->items as $item) {
            $name = (string) $item->key;
            $schema = $schema->addProperties(self::of($item->type)->objectId($name));

            if (! $item->optional) {
                $required[] = $name;
            }
        }

        $schema = $required === [] ? $schema : $schema->required(...$required);

        return $shape->sealed ? $schema->additionalProperties(false) : $schema;
    }

    private static function union(UnionType $union): Schema
    {
        $values = [];

        foreach ($union->types as $type) {
            $value = match (true) {
                $type instanceof LiteralType => [$type->value],
                $type instanceof NamedType && $type->name === 'true' => [true],
                $type instanceof NamedType && $type->name === 'false' => [false],
                default => null,
            };

            if ($value === null) {
                return Schema::create()->anyOf(...array_map(self::of(...), $union->types));
            }

            $values[] = $value[0];
        }

        // One type for every value, else no type: `'auto'|1|true` accepts the three.
        $types = array_unique(array_map(get_debug_type(...), $values));

        return count($types) === 1 ? self::literal($values[0])->enum(...$values) : Schema::create()->enum(...$values);
    }

    private static function literal(int|float|string|bool $value): Schema
    {
        return match (true) {
            is_int($value) => Schema::integer(),
            is_float($value) => Schema::number(),
            is_bool($value) => Schema::boolean(),
            default => Schema::string(),
        };
    }

    private static function bound(Type $type): ?int
    {
        return $type instanceof LiteralType && is_int($type->value) ? $type->value : null;
    }
}
