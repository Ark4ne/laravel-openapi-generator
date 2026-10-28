<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Strategies;

use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Illuminate\Support\Str;

/**
 * `name`: route name; `action`: `UserController.show`; `method_path`: `getApiUsersByUser`.
 *
 * `name` and `action` fall back to `method_path` when the route has no name or no controller. Several methods of
 * one route share its name: the OperationIdPass (E7) makes the identifiers unique.
 */
final readonly class BuiltInOperationIdStrategy implements OperationIdStrategy
{
    public const BY = ['name', 'action', 'method_path'];

    public function __construct(
        private string $by = 'name',
    ) {}

    public function operationId(OperationContext $operation): string
    {
        $route = $operation->route;

        return match (true) {
            $this->by === 'name' && $route->name !== null => $route->name,
            $this->by === 'action' && $route->controller !== null => RouteValues::basename($route->controller) . ".{$route->action}",
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
}
