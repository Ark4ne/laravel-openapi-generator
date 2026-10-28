<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Strategies;

use Ark4ne\OpenApi\Extraction\OperationContext;

/**
 * `doc`: summary of the PHPDoc; `name`: route name; `uri`: path; `controller`: `UserController@show`. The `pattern`
 * option keeps its first group.
 */
final readonly class BuiltInSummaryStrategy implements SummaryStrategy
{
    public const BY = ['doc', 'name', 'uri', 'controller'];

    public function __construct(
        private string $by = 'doc',
        private ?string $pattern = null,
    ) {}

    public function summary(OperationContext $operation): ?string
    {
        $value = match ($this->by) {
            'doc' => $operation->doc?->summary(),
            'uri' => $operation->path,
            'controller' => $operation->route->controller === null ? null : RouteValues::controller($operation->route->controller) . "Controller@{$operation->route->action}",
            default => $operation->route->name,
        };

        if ($value === null || $this->pattern === null) {
            return $value;
        }

        return RouteValues::match($this->pattern, $value) ?? $value;
    }
}
