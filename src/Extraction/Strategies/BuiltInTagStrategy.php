<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Strategies;

use Ark4ne\OpenApi\Extraction\OperationContext;

/**
 * One tag per operation, read from the `controller` class, the `uri` or the route `name`.
 *
 * With `pattern`, its first group is the tag. Without, or when it does not match: the controller basename without
 * `Controller`, the first segment of the URI after `api` and the version, the first segment of the name after `api`.
 */
final readonly class BuiltInTagStrategy implements TagStrategy
{
    public const BY = ['controller', 'uri', 'name'];

    public function __construct(
        private string $by = 'controller',
        private ?string $pattern = null,
    ) {}

    public function tags(OperationContext $operation): array
    {
        $value = RouteValues::of($this->by, $operation);

        if ($value === null || $value === '') {
            return [];
        }

        $tag = ($this->pattern === null ? null : RouteValues::match($this->pattern, $value)) ?? match ($this->by) {
            'controller' => RouteValues::controller($value),
            'uri' => RouteValues::resource($value),
            default => RouteValues::resource(str_replace('.', '/', $value)),
        };

        return $tag === null ? [] : [$tag];
    }
}
