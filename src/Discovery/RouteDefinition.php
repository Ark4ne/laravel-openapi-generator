<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Discovery;

use Illuminate\Routing\Route;

/**
 * A route kept by the discovery, with everything read from the router. Nothing else reads the router.
 *
 * The collector and the filters come with E2.
 */
final readonly class RouteDefinition
{
    /**
     * @param list<string> $methods upper case
     * @param class-string|null $controller
     * @param list<string> $middlewares resolved, groups included
     * @param array<string, string> $wheres parameter → regular expression
     * @param array<string, string> $bindingFields parameter → custom key (`{post:slug}`)
     * @param array<string, mixed> $defaults
     */
    public function __construct(
        public Route $route,
        public string $uri,
        public array $methods,
        public ?string $name = null,
        public ?string $domain = null,
        public ?string $controller = null,
        public ?string $action = null,
        public array $middlewares = [],
        public array $wheres = [],
        public array $bindingFields = [],
        public array $defaults = [],
    ) {}

    /**
     * `GET /users/{user}` or the route name, for the diagnostics.
     */
    public function label(): string
    {
        return '';
    }
}
