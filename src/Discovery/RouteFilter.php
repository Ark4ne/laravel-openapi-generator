<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Discovery;

use Ark4ne\OpenApi\Configuration\RouteFilterConfiguration;
use Ark4ne\OpenApi\Configuration\RouteMasks;
use Illuminate\Routing\RedirectController;
use Illuminate\Routing\Route;
use Illuminate\Routing\ViewController;
use Illuminate\Support\Str;

/**
 * Applies `documents.<name>.routes` to a route of the router, before it is unfolded into definitions.
 */
final readonly class RouteFilter
{
    /**
     * Controllers of `Route::redirect()`, `Route::permanentRedirect()` and `Route::view()`.
     */
    private const LEFT_OUT_CONTROLLERS = [RedirectController::class, ViewController::class];

    public function __construct(
        private RouteFilterConfiguration $configuration,
    ) {}

    /**
     * Whether the route belongs to the document. `#[Exclude]` is read by the collector, which reflects the action.
     *
     * @param list<string> $middlewares declared middlewares, groups expanded
     * @param list<string> $middlewareClasses the same middlewares resolved to their class
     */
    public function accepts(Route $route, array $middlewares = [], array $middlewareClasses = []): bool
    {
        if ($route->isFallback || $this->isLeftOutController($route)) {
            return false;
        }

        $uri = self::uri($route->uri());

        if (Str::is(array_map(self::uri(...), $this->configuration->internal), $uri)) {
            return false;
        }

        $include = $this->configuration->include;

        if (! $include->isEmpty() && ! $this->matches($include, $route, $uri, [...$middlewares, ...$middlewareClasses])) {
            return false;
        }

        return ! $this->matches($this->configuration->exclude, $route, $uri, [...$middlewares, ...$middlewareClasses]);
    }

    /**
     * The methods of the route the document describes, in upper case: ignored methods removed.
     *
     * @return list<string>
     */
    public function methods(Route $route): array
    {
        $ignored = array_map(strtoupper(...), $this->configuration->ignoredMethods);
        $methods = array_unique(array_map(strtoupper(...), $route->methods()));

        return array_values(array_diff($methods, $ignored));
    }

    /**
     * @param list<string> $middlewares
     */
    private function matches(RouteMasks $masks, Route $route, string $uri, array $middlewares): bool
    {
        $name = $route->getName();
        $domain = $route->getDomain();

        return Str::is(array_map(self::uri(...), $masks->uri), $uri)
            || ($name !== null && Str::is($masks->name, $name))
            || ($domain !== null && Str::is($masks->domain, $domain))
            || array_filter($middlewares, static fn(string $middleware): bool => Str::is($masks->middleware, $middleware)) !== [];
    }

    private function isLeftOutController(Route $route): bool
    {
        $controller = $route->getAction('controller');

        if (! is_string($controller)) {
            return false;
        }

        return in_array(ltrim(Str::before($controller, '@'), '\\'), self::LEFT_OUT_CONTROLLERS, true);
    }

    private static function uri(string $uri): string
    {
        return trim($uri, '/');
    }
}
