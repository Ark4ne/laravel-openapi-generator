<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Discovery;

use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Illuminate\Routing\Route;

/**
 * One operation to describe: one HTTP method of one path of a route kept by the discovery. Nothing else reads the
 * router.
 *
 * A route with optional parameters (`users/{id?}`) gives one definition per path, a route with several methods
 * (`any()`, `match()`) one definition per method.
 */
final readonly class RouteDefinition
{
    /**
     * @param HttpMethod|string $method a fixed method of a Path Item, or another method in upper case (`PURGE`,
     *        written in `additionalOperations` from 3.2)
     * @param string $path OpenAPI path (`/users/{user}`): leading slash, no optional mark, no custom binding key
     * @param class-string|null $controller `null` for a closure
     * @param string|null $action controller method (`__invoke` for an invokable controller)
     * @param list<ParameterDescription> $parameters parameters of the path, in the order of the path
     * @param list<ParameterDescription> $domainParameters parameters of the domain (`{account}.example.com`)
     * @param list<string> $middlewares declared middlewares, groups expanded, aliases and arguments kept
     *        (`auth:sanctum`), excluded middlewares removed
     * @param list<string> $middlewareClasses the same middlewares resolved to their class (`…\Authenticate:sanctum`)
     */
    public function __construct(
        public Route $route,
        public HttpMethod|string $method,
        public string $path,
        public ?string $name = null,
        public ?string $domain = null,
        public ?string $controller = null,
        public ?string $action = null,
        public array $parameters = [],
        public array $domainParameters = [],
        public array $middlewares = [],
        public array $middlewareClasses = [],
        public bool $withTrashed = false,
        public bool $scopeBindings = false,
    ) {}

    /**
     * `GET /users/{user}`, for the diagnostics.
     */
    public function label(): string
    {
        return self::labelOf($this->method, $this->path);
    }

    /**
     * `GET /users/{user}`, for an operation that has no definition yet.
     */
    public static function labelOf(HttpMethod|string $method, string $path): string
    {
        $method = $method instanceof HttpMethod ? strtoupper($method->value) : $method;

        return "{$method} {$path}";
    }

    /**
     * A fixed method of a Path Item, or another method in upper case (`PURGE`).
     */
    public static function method(string $name): HttpMethod|string
    {
        return HttpMethod::tryFrom(strtolower($name)) ?? strtoupper($name);
    }

    public function isClosure(): bool
    {
        return $this->controller === null;
    }
}
