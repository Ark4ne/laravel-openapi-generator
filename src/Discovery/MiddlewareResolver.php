<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Discovery;

use Illuminate\Routing\MiddlewareNameResolver;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Throwable;

/**
 * Middlewares of a route, as declared and as resolved to their class, with the exclusions applied as Laravel applies
 * them (`withoutMiddleware()` by name, alias, group, class or parent class).
 */
final readonly class MiddlewareResolver
{
    public function __construct(
        private Router $router,
    ) {}

    /**
     * Declared middlewares, groups expanded, aliases and arguments kept (`auth:sanctum`), excluded middlewares and
     * closures removed, without duplicates.
     *
     * @return list<string>
     */
    public function declared(Route $route): array
    {
        return array_values(array_unique(array_keys($this->resolve($route))));
    }

    /**
     * The same middlewares resolved to their class, with their arguments (`…\Authenticate:sanctum`), in declaration
     * order.
     *
     * @return list<string>
     */
    public function classes(Route $route): array
    {
        return array_values(array_unique($this->resolve($route)));
    }

    /**
     * @return array<string, string> declared middleware → its class
     */
    private function resolve(Route $route): array
    {
        $aliases = $this->router->getMiddleware();
        $groups = $this->router->getMiddlewareGroups();
        $excluded = [];

        foreach (self::strings($route->excludedMiddleware()) as $name) {
            array_push($excluded, ...self::strings((array) MiddlewareNameResolver::resolve($name, $aliases, $groups)));
        }

        $middlewares = [];

        foreach (self::strings($this->gather($route)) as $name) {
            foreach (self::strings((array) MiddlewareNameResolver::resolve($name, [], $groups)) as $declared) {
                $class = MiddlewareNameResolver::resolve($declared, $aliases, []);

                if (is_string($class) && ! self::isExcluded($class, $excluded)) {
                    $middlewares[$declared] = $class;
                }
            }
        }

        return $middlewares;
    }

    /**
     * The route middlewares and those of its controller; only the route's when the controller cannot be built.
     *
     * @return array<array-key, mixed>
     */
    private function gather(Route $route): array
    {
        try {
            return $route->gatherMiddleware();
        } catch (Throwable) {
            // Laravel caches an empty list before building the controller: the route would run without middleware.
            $route->computedMiddleware = null;

            return $route->middleware();
        }
    }

    /**
     * Router::resolveMiddleware(): excluded by its exact value, its class, or a parent class.
     *
     * @param list<string> $excluded resolved exclusions
     */
    private static function isExcluded(string $middleware, array $excluded): bool
    {
        $class = Str::before($middleware, ':');

        if (in_array($middleware, $excluded, true) || in_array($class, $excluded, true)) {
            return true;
        }

        foreach ($excluded as $exclude) {
            if (class_exists($class) && class_exists($exclude) && is_subclass_of($class, $exclude)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return list<string>
     */
    private static function strings(array $values): array
    {
        return array_values(array_filter($values, is_string(...)));
    }
}
