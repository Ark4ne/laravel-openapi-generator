<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Discovery;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Support\Enums;
use Ark4ne\OpenApiWriter\Objects\Schema;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

/**
 * Schemas of the parameters of a path or a domain, from the `where` constraints and the bindings of the action.
 *
 * - `whereNumber` → `integer`, `whereUuid` → `format: uuid`, `whereIn` → `enum`, `whereAlpha`, `whereAlphaNumeric`,
 *   `whereUlid` and any other expression → `pattern`;
 * - a bound model (implicit binding) → the type of its route key (`{post:slug}` → the `slug` column), from the key
 *   type, the casts and `HasUuids` / `HasUlids`; `boundTo` is the model;
 * - a bound backed enum → `enum` of its values; `boundTo` is the enum (made a component by E7);
 * - a scalar argument (`int $page`) → its type.
 *
 * The action is reflected through the route; when that fails, the parameter stays a string and a diagnostic is added.
 */
final readonly class PathParameterResolver
{
    private const INTEGER = ['[0-9]+', '\d+'];

    private const UUID = '[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}';

    /**
     * Digits only: `[0-9]` or `[1-9]` classes, `\d`, digits and quantifiers.
     */
    private const DIGITS = '/^(?:\[\d-\d]|\\\\d|[\d{},+*?])+$/';

    private const ULID = '[0-7][0-9a-hjkmnp-tv-zA-HJKMNP-TV-Z]{25}';

    /**
     * Literal values joined by `|`, as written by `whereIn()`.
     */
    private const LITERALS = '/^[\w.~-]+(?:\|[\w.~-]+)*$/u';

    /**
     * @param list<string> $names parameter names, in the order of the path or the domain
     * @param string $label route label for the diagnostics (`GET /users/{user}`)
     *
     * @return list<ParameterDescription>
     */
    public function resolve(Route $route, array $names, GenerationContext $context, string $label): array
    {
        $arguments = $this->arguments($route, $names, $context, $label);

        return array_map(fn(string $name): ParameterDescription => $this->parameter($route, $name, $arguments), $names);
    }

    /**
     * @param list<ReflectionParameter> $arguments
     */
    private function parameter(Route $route, string $name, array $arguments): ParameterDescription
    {
        $parameter = ParameterDescription::path($name, Schema::string());
        $type = self::argument($arguments, $name)?->getType();

        if ($type instanceof ReflectionNamedType) {
            $parameter = $this->bind($parameter, $route, $type);
        }

        $where = $route->wheres[$name] ?? null;

        if (is_string($where) && ! self::isEnum($parameter->boundTo) && ! self::keepsInteger($parameter, $where)) {
            $parameter = $parameter->schema(self::constraint($where));
        }

        return $parameter;
    }

    private function bind(ParameterDescription $parameter, Route $route, ReflectionNamedType $type): ParameterDescription
    {
        $class = $type->getName();

        return match (true) {
            $class === 'int' => $parameter->schema(Schema::integer()),
            $class === 'float' => $parameter->schema(Schema::number()),
            is_subclass_of($class, BackedEnum::class) => $parameter->schema(self::enum($class))->boundTo($class),
            is_subclass_of($class, Model::class) => $parameter->schema(self::model($class, $route->bindingFieldFor($parameter->name)))->boundTo($class),
            default => $parameter,
        };
    }

    /**
     * Arguments of the action; none when it cannot be reflected.
     *
     * @param list<string> $names
     *
     * @return list<ReflectionParameter>
     */
    private function arguments(Route $route, array $names, GenerationContext $context, string $label): array
    {
        if ($names === []) {
            return [];
        }

        $controller = $route->getAction('controller');

        try {
            // Laravel returns no parameter, without error, for a controller method that does not exist.
            if (is_string($controller) && ! self::isMethod($controller)) {
                throw new ReflectionException();
            }

            return array_values(array_filter($route->signatureParameters(), static fn(mixed $argument): bool => $argument instanceof ReflectionParameter));
        } catch (Throwable) {
            $context->diagnostics->warning(
                Stage::Discovery,
                "cannot reflect the action {$route->getActionName()}, path parameters are written as strings",
                $label,
            );

            return [];
        }
    }

    /**
     * @param class-string<BackedEnum> $enum
     */
    private static function enum(string $enum): Schema
    {
        return Schema::create()->type(Enums::type($enum))->enum(...Enums::values($enum));
    }

    /**
     * Type of the column a model is bound by: its cast, else its key type when it is the key.
     *
     * @param class-string<Model> $class
     */
    private static function model(string $class, ?string $field): Schema
    {
        try {
            $model = new $class();
        } catch (Throwable) {
            return Schema::string();
        }

        $field ??= $model->getRouteKeyName();
        $cast = $model->getCasts()[$field] ?? null;

        if (is_string($cast)) {
            return self::cast($cast);
        }

        if ($model->usesUniqueIds() && in_array($field, $model->uniqueIds(), true)) {
            return self::uniqueId($model->newUniqueId());
        }

        if ($field !== $model->getKeyName()) {
            return Schema::string();
        }

        return in_array($model->getKeyType(), ['int', 'integer'], true) ? Schema::integer() : Schema::string();
    }

    private static function cast(string $cast): Schema
    {
        return match (true) {
            in_array($cast, ['int', 'integer'], true) => Schema::integer(),
            in_array($cast, ['real', 'float', 'double'], true) => Schema::number(),
            in_array($cast, ['bool', 'boolean'], true) => Schema::boolean(),
            is_subclass_of($cast, BackedEnum::class) => self::enum($cast),
            default => Schema::string(),
        };
    }

    private static function uniqueId(mixed $sample): Schema
    {
        $sample = is_string($sample) ? $sample : '';

        return match (true) {
            Str::isUuid($sample) => Schema::string()->format('uuid'),
            Str::isUlid($sample) => Schema::string()->pattern('^' . self::ULID . '$'),
            default => Schema::string(),
        };
    }

    private static function constraint(string $expression): Schema
    {
        if (in_array($expression, self::INTEGER, true)) {
            return Schema::integer();
        }

        if ($expression === self::UUID) {
            return Schema::string()->format('uuid');
        }

        if (preg_match(self::LITERALS, $expression) === 1) {
            $values = explode('|', $expression);

            // `01` is not the integer 1: the route would refuse `1`.
            $integers = array_filter($values, static fn(string $value): bool => (string) (int) $value === $value);

            return $integers === $values
                ? Schema::integer()->enum(...array_map(intval(...), $values))
                : Schema::string()->enum(...$values);
        }

        return Schema::string()->pattern(str_contains($expression, '|') ? "^(?:{$expression})$" : "^{$expression}$");
    }

    /**
     * The argument of the action a route parameter is given to: same name, or its snake case (as Laravel binds it).
     *
     * @param list<ReflectionParameter> $arguments
     */
    private static function argument(array $arguments, string $name): ?ReflectionParameter
    {
        foreach ($arguments as $argument) {
            if (in_array($name, [$argument->getName(), Str::snake($argument->getName())], true)) {
                return $argument;
            }
        }

        return null;
    }

    /**
     * Whether `Controller@method` can be called.
     */
    private static function isMethod(string $controller): bool
    {
        [$class, $method] = Str::parseCallback($controller, '__invoke');

        return is_string($class) && is_string($method) && (method_exists($class, $method) || method_exists($class, '__call'));
    }

    /**
     * An integer parameter (bound model key, `int` argument) constrained to digits stays an integer: `[0-9]{1,6}`,
     * `\d{4}`, `[1-9][0-9]*`.
     */
    private static function keepsInteger(ParameterDescription $parameter, string $expression): bool
    {
        return $parameter->schema->toArray() === ['type' => 'integer'] && preg_match(self::DIGITS, $expression) === 1;
    }

    private static function isEnum(?string $class): bool
    {
        return $class !== null && is_subclass_of($class, BackedEnum::class);
    }
}
