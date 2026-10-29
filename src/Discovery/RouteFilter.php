<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Discovery;

use Ark4ne\OpenApi\Configuration\RouteFilterConfiguration;
use Ark4ne\OpenApi\Configuration\RouteMasks;
use Closure;
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
     * Resolving the middlewares may build the controller: `$middlewares` is called only when a middleware mask has to
     * be checked, after every other criterion.
     *
     * @param (Closure(): list<string>)|null $middlewares declared middlewares and their classes
     */
    public function accepts(Route $route, ?Closure $middlewares = null): bool
    {
        if ($route->isFallback || $this->isLeftOutController($route)) {
            return false;
        }

        $uri = self::uri($route->uri());

        if (Str::is(array_map(self::uri(...), $this->configuration->internal), $uri)) {
            return false;
        }

        $middlewares ??= static fn(): array => [];
        $include = $this->configuration->include;

        if (! $include->isEmpty() && ! $this->matches($include, $route, $uri, $middlewares)) {
            return false;
        }

        return ! $this->matches($this->configuration->exclude, $route, $uri, $middlewares);
    }

    /**
     * The methods of the route the document describes, in upper case: ignored methods removed.
     *
     * @return list<string>
     */
    public function methods(Route $route): array
    {
        $methods = array_unique(array_map(strtoupper(...), $route->methods()));

        return array_values(array_diff($methods, $this->configuration->ignoredMethods));
    }

    /**
     * A middleware mask matches the middleware with or without its arguments (`auth`, `…\Authenticate`).
     *
     * @param Closure(): list<string> $middlewares
     */
    private function matches(RouteMasks $masks, Route $route, string $uri, Closure $middlewares): bool
    {
        $name = $route->getName();
        $domain = $route->getDomain();

        if (Str::is(array_map(self::uri(...), $masks->uri), $uri)
            || ($name !== null && Str::is($masks->name, $name))
            || ($domain !== null && Str::is($masks->domain, $domain))) {
            return true;
        }

        if ($masks->middleware === []) {
            return false;
        }

        foreach ($middlewares() as $middleware) {
            if (Str::is($masks->middleware, $middleware) || Str::is($masks->middleware, Str::before($middleware, ':'))) {
                return true;
            }
        }

        return false;
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
