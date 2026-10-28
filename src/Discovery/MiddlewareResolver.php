<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Discovery;

use Illuminate\Routing\MiddlewareNameResolver;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Throwable;

/**
 * Middlewares of a route, as declared and as resolved by the router.
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
        $groups = $this->router->getMiddlewareGroups();
        $excluded = self::strings($route->excludedMiddleware());
        $middlewares = [];

        foreach (self::strings($this->gather($route)) as $name) {
            foreach ((array) MiddlewareNameResolver::resolve($name, [], $groups) as $middleware) {
                if (is_string($middleware) && ! in_array($middleware, $excluded, true)) {
                    $middlewares[] = $middleware;
                }
            }
        }

        return array_values(array_unique($middlewares));
    }

    /**
     * The middlewares resolved by the router to their class, with their arguments (`…\Authenticate:sanctum`).
     *
     * @return list<string>
     */
    public function classes(Route $route): array
    {
        try {
            $middlewares = $this->router->gatherRouteMiddleware($route);
        } catch (Throwable) {
            return [];
        }

        return array_values(array_unique(self::strings($middlewares)));
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
            return $route->middleware();
        }
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
