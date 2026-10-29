<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Discovery;

use Ark4ne\OpenApi\Attributes\Exclude;
use Ark4ne\OpenApi\Configuration\RouteOrder;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Ark4ne\OpenApiWriter\Enums\Strictness;
use Ark4ne\OpenApiWriter\Enums\Version;
use Closure;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;

/**
 * Reads the router once and returns the operations of a document.
 *
 * Steps, per route in registration order: filter (`documents.<name>.routes`, `#[Exclude]`), methods (ignored methods
 * removed; `QUERY` and the other methods dropped with a warning before 3.2 in lenient mode, kept for the writer to
 * reject in strict mode), paths (one per combination of optional parameters), parameters. An operation (method and
 * path) already given by an earlier route is left out with a warning. Then `routes.order`.
 */
final readonly class RouteCollector
{
    private const PARAMETER = '/\{(\w+)(\?)?}/';

    public function __construct(
        private Router $router,
        private MiddlewareResolver $middlewares,
        private PathParameterResolver $parameters,
    ) {}

    /**
     * @return list<RouteDefinition>
     */
    public function collect(GenerationContext $context): array
    {
        $filter = new RouteFilter($context->document->routes);
        $definitions = [];
        /** @var array<string, string> $discovered label → action that gave it */
        $discovered = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $middlewares = fn(): array => [...$this->middlewares->declared($route), ...$this->middlewares->classes($route)];

            if (! $filter->accepts($route, $middlewares) || self::isExcluded($route)) {
                continue;
            }

            $paths = self::paths($route->uri());
            $methods = self::methods($filter->methods($route), $paths[array_key_last($paths)], $context);

            if ($methods === []) {
                continue;
            }

            [$controller, $action] = self::action($route);
            $label = RouteDefinition::labelOf($methods[0], $paths[array_key_last($paths)]);
            $domainNames = self::names($route->getDomain() ?? '');
            $parameters = $this->parameters->resolve($route, [...self::names($route->uri()), ...$domainNames], $context, $label);
            $domainParameters = self::only($parameters, $domainNames);
            $middlewares = $this->middlewares->declared($route);
            $middlewareClasses = $this->middlewares->classes($route);

            foreach ($paths as $path) {
                $pathParameters = self::only($parameters, self::names($path));

                foreach ($methods as $method) {
                    $label = RouteDefinition::labelOf($method, $path);

                    if (isset($discovered[$label])) {
                        $context->diagnostics->warning(
                            Stage::Discovery,
                            "already discovered from {$discovered[$label]}, {$route->getActionName()} left out for this path",
                            $label,
                        );

                        continue;
                    }

                    $discovered[$label] = $route->getActionName();
                    $definitions[] = new RouteDefinition(
                        $route,
                        $method,
                        $path,
                        $route->getName(),
                        $route->getDomain(),
                        $controller,
                        $action,
                        $pathParameters,
                        $domainParameters,
                        $middlewares,
                        $middlewareClasses,
                        $route->allowsTrashedBindings(),
                        $route->enforcesScopedBindings(),
                    );
                }
            }
        }

        if ($context->document->routes->order === RouteOrder::Path) {
            usort($definitions, static fn(RouteDefinition $a, RouteDefinition $b): int => strcmp($a->path, $b->path));
        }

        return $definitions;
    }

    /**
     * OpenAPI paths of a URI: without its optional parameters, then with one more each time.
     *
     * @return non-empty-list<string>
     */
    private static function paths(string $uri): array
    {
        $uri = '/' . trim($uri, '/');
        $paths = [];

        if (preg_match_all(self::PARAMETER, $uri, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                if (isset($match[2])) {
                    $paths[] = substr($uri, 0, $match[0][1]);
                }
            }
        }

        $paths[] = $uri;

        return array_map(
            static fn(string $path): string => '/' . trim((string) preg_replace(self::PARAMETER, '{$1}', $path), '/'),
            $paths,
        );
    }

    /**
     * @param list<ParameterDescription> $parameters
     * @param list<string> $names
     *
     * @return list<ParameterDescription>
     */
    private static function only(array $parameters, array $names): array
    {
        return array_values(array_filter($parameters, static fn(ParameterDescription $parameter): bool => in_array($parameter->name, $names, true)));
    }

    /**
     * @return list<string>
     */
    private static function names(string $template): array
    {
        preg_match_all(self::PARAMETER, $template, $matches);

        return $matches[1];
    }

    /**
     * @param list<string> $methods upper case
     *
     * @return list<HttpMethod|string>
     */
    private static function methods(array $methods, string $path, GenerationContext $context): array
    {
        $document = $context->document;
        $kept = [];

        foreach ($methods as $name) {
            $method = RouteDefinition::method($name);
            $additional = ! $method instanceof HttpMethod;

            if (($additional || $method === HttpMethod::Query)
                && ! $document->version->isAtLeast(Version::V3_2_0)
                && $document->strictness === Strictness::Lenient) {
                $context->diagnostics->warning(
                    Stage::Discovery,
                    $additional
                        ? "the {$name} method is written in additionalOperations from OpenAPI 3.2, operation left out"
                        : "the {$name} method is written from OpenAPI 3.2, operation left out",
                    RouteDefinition::labelOf($method, $path),
                );

                continue;
            }

            $kept[] = $method;
        }

        return $kept;
    }

    /**
     * @return array{class-string|null, string|null}
     */
    private static function action(Route $route): array
    {
        $controller = $route->getAction('controller');

        if (! is_string($controller)) {
            return [null, null];
        }

        [$class, $method] = Str::parseCallback(ltrim($controller, '\\'), '__invoke');

        /** @var class-string $class */
        return [$class, $method];
    }

    /**
     * `#[Exclude]` on the controller, on its method, or on the closure.
     */
    private static function isExcluded(Route $route): bool
    {
        [$controller, $method] = self::action($route);

        if ($controller === null) {
            $uses = $route->getAction('uses');

            return $uses instanceof Closure && (new ReflectionFunction($uses))->getAttributes(Exclude::class) !== [];
        }

        if (! class_exists($controller)) {
            return false;
        }

        if ((new ReflectionClass($controller))->getAttributes(Exclude::class) !== []) {
            return true;
        }

        return $method !== null
            && method_exists($controller, $method)
            && (new ReflectionMethod($controller, $method))->getAttributes(Exclude::class) !== [];
    }
}
