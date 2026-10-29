<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Strategies;

use Ark4ne\OpenApi\Extraction\OperationContext;

/**
 * The values of a route the built-in strategies read, and the `pattern` option they share.
 */
final class RouteValues
{
    /**
     * `controller`: the controller class; `uri`: the path without its leading slash; `name`: the route name.
     */
    public static function of(string $by, OperationContext $operation): ?string
    {
        return match ($by) {
            'controller' => $operation->route->controller,
            'uri' => ltrim($operation->path, '/'),
            'name' => $operation->route->name,
            default => null,
        };
    }

    /**
     * The first group of the pattern, else its whole match; `null` when it does not match.
     */
    public static function match(string $pattern, string $value): ?string
    {
        if (preg_match($pattern, $value, $matches) !== 1) {
            return null;
        }

        $value = $matches[1] ?? $matches[0];

        return $value === '' ? null : $value;
    }

    /**
     * First segment of a path that is neither `api`, a version (`v1`) nor a parameter: `api/v1/users/{user}` → `users`.
     */
    public static function resource(string $uri): ?string
    {
        foreach (explode('/', trim($uri, '/')) as $segment) {
            if ($segment !== '' && $segment !== 'api' && preg_match('/^v\d+$|^\{/', $segment) !== 1) {
                return $segment;
            }
        }

        return null;
    }

    /**
     * Class basename without its `Controller` suffix: `App\Http\Controllers\UserController` → `User`.
     */
    public static function controller(string $class): string
    {
        $basename = self::basename($class);

        return str_ends_with($basename, 'Controller') && $basename !== 'Controller' ? substr($basename, 0, -10) : $basename;
    }

    /**
     * `App\Http\Controllers\UserController` → `UserController`.
     */
    public static function basename(string $class): string
    {
        return substr($class, (int) strrpos('\\' . $class, '\\'));
    }
}
