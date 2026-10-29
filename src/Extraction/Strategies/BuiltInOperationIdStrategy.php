<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Strategies;

use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Illuminate\Support\Str;

/**
 * `name`: route name; `action`: `UserController.show`; `method_path`: `getApiUsersByUser`; `method_name`: `GET:users.show`
 * (the route name prefixed by the method, as 0.6 wrote it: one identifier per method of a route that has several).
 *
 * `name`, `action` and `method_name` fall back to `method_path` when the route has no name or no controller. Several methods of
 * one route share its name: the OperationIdPass (E7) makes the identifiers unique.
 */
final readonly class BuiltInOperationIdStrategy implements OperationIdStrategy
{
    public const BY = ['name', 'action', 'method_path', 'method_name'];

    public function __construct(
        private string $by = 'name',
    ) {}

    public function operationId(OperationContext $operation): string
    {
        $route = $operation->route;

        return match (true) {
            $this->by === 'name' && $route->name !== null => $route->name,
            $this->by === 'action' && $route->controller !== null => RouteValues::basename($route->controller) . ".{$route->action}",
            $this->by === 'method_name' && $route->name !== null => self::methodName($operation->method, $route->name),
            default => self::methodPath($operation->method, $operation->path),
        };
    }

    /**
     * `GET /api/users/{user}` → `getApiUsersByUser`.
     */
    public static function methodPath(HttpMethod|string $method, string $path): string
    {
        $method = $method instanceof HttpMethod ? $method->value : strtolower($method);
        $path = (string) preg_replace('/\{(\w+)}/', 'by $1', $path);

        return Str::camel($method . ' ' . (string) preg_replace('/[^A-Za-z0-9]+/', ' ', $path));
    }

    /**
     * `GET`, `api.users.show` → `GET:api.users.show`, the identifier of 0.6.
     */
    public static function methodName(HttpMethod|string $method, string $name): string
    {
        return strtoupper($method instanceof HttpMethod ? $method->value : $method) . ":{$name}";
    }
}
