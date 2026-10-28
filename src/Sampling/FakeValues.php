<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Types\ArrayShapeType;
use Ark4ne\OpenApi\Types\GenericType;
use Ark4ne\OpenApi\Types\ListType;
use Ark4ne\OpenApi\Types\LiteralType;
use Ark4ne\OpenApi\Types\NamedType;
use Ark4ne\OpenApi\Types\NullableType;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApi\Types\TypeResolver;
use Ark4ne\OpenApi\Types\UnionType;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionClass;
use stdClass;
use Throwable;
use UnitEnum;

/**
 * A representative value for a declared type: `int` → 1, `string` → `string`, a fixed date, the first case of an
 * enum, two items for a list, a model filled from its declared types, a FakeObject for any other class.
 *
 * Objects are nested two levels deep at most; deeper, the value is `null`.
 */
final readonly class FakeValues
{
    public const DATE = '2024-01-01T00:00:00+00:00';

    private const DEPTH = 2;

    public function __construct(
        private TypeResolver $types,
    ) {}

    public function for(Type $type, int $depth = 0): mixed
    {
        return match (true) {
            $type instanceof NullableType => $this->for($type->type, $depth),
            $type instanceof LiteralType => $type->value,
            $type instanceof UnionType => $this->for($type->types[0], $depth),
            $type instanceof ListType => self::twice(fn(): mixed => $this->for($type->value, $depth)),
            $type instanceof GenericType => $this->generic($type, $depth),
            $type instanceof ArrayShapeType => $this->shape($type, $depth),
            $type instanceof NamedType => $this->named($type->name, $depth),
            default => null,
        };
    }

    /**
     * A model filled from its `@property` and its casts, without database.
     *
     * @template T of Model
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    public function model(string $class, int $depth = 0): ?Model
    {
        try {
            $model = new $class();
        } catch (Throwable) {
            return null;
        }

        $attributes = [];

        foreach ($this->types->properties(new ReflectionClass($class)) as $name => $type) {
            $attributes[$name] = $this->for($type, $depth + 1);
        }

        foreach ($model->getCasts() as $name => $cast) {
            $value = self::cast($cast);

            if ($value !== null || ! array_key_exists($name, $attributes)) {
                $attributes[$name] = $value;
            }
        }

        try {
            return $model->forceFill($attributes);
        } catch (Throwable) {
            return $model;
        }
    }

    private function named(string $name, int $depth): mixed
    {
        return match ($name) {
            'int', 'positive-int', 'non-negative-int', 'array-key' => 1,
            'negative-int', 'non-positive-int' => -1,
            'float', 'numeric' => 1.5,
            'bool', 'true' => true,
            'false' => false,
            'null', 'void', 'never', 'mixed', 'resource', 'callable' => null,
            'array', 'iterable', 'non-empty-array' => [],
            'object' => new stdClass(),
            default => in_array($name, NamedType::BUILTIN, true) ? 'string' : $this->object($name, $depth),
        };
    }

    private function object(string $class, int $depth): mixed
    {
        return match (true) {
            enum_exists($class) => $class::cases()[0] ?? null,
            is_a($class, DateTimeImmutable::class, true) => CarbonImmutable::parse(self::DATE),
            is_a($class, DateTimeInterface::class, true) => Carbon::parse(self::DATE),
            $depth >= self::DEPTH || ! class_exists($class) => null,
            is_subclass_of($class, Model::class) => $this->model($class, $depth),
            is_a($class, Collection::class, true) => new Collection(),
            default => new FakeObject($class, $this, $this->types, $depth + 1),
        };
    }

    private function generic(GenericType $type, int $depth): mixed
    {
        $last = $type->arguments[array_key_last($type->arguments)];

        return match (true) {
            $type->name === 'int' => 1,
            in_array($type->name, ['array', 'iterable', 'non-empty-array'], true) => self::twice(fn(): mixed => $this->for($last, $depth)),
            is_a($type->name, Collection::class, true) => new $type->name(self::twice(fn(): mixed => $this->for($last, $depth))),
            default => $this->named($type->name, $depth),
        };
    }

    /**
     * @return array<array-key, mixed>
     */
    private function shape(ArrayShapeType $shape, int $depth): array
    {
        $values = [];

        foreach ($shape->items as $position => $item) {
            $values[$item->key ?? $position] = $this->for($item->type, $depth);
        }

        return $values;
    }

    /**
     * The value of an attribute for its cast; `null` for a cast without fixed value (`array`, a custom cast).
     */
    private static function cast(string $cast): mixed
    {
        $name = strtolower(explode(':', $cast, 2)[0]);

        return match (true) {
            in_array($name, ['int', 'integer', 'timestamp'], true) => 1,
            in_array($name, ['real', 'float', 'double'], true) => 1.5,
            $name === 'decimal' => '1.00',
            in_array($name, ['bool', 'boolean'], true) => true,
            in_array($name, ['string', 'hashed', 'encrypted'], true) => 'string',
            in_array($name, ['date', 'datetime', 'custom_datetime'], true) => Carbon::parse(self::DATE),
            in_array($name, ['immutable_date', 'immutable_datetime', 'immutable_custom_datetime'], true) => CarbonImmutable::parse(self::DATE),
            enum_exists($cast) && is_subclass_of($cast, UnitEnum::class) => $cast::cases()[0] ?? null,
            default => null,
        };
    }

    /**
     * @param Closure(): mixed $value
     *
     * @return list<mixed>
     */
    private static function twice(Closure $value): array
    {
        return [$value(), $value()];
    }
}
