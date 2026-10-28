<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Strategies;

use Ark4ne\OpenApi\Extraction\OperationContext;

/**
 * Parent tag of the tags of an operation, read from the `controller` class, the `uri` or the route `name`.
 *
 * With `pattern`, its first group is the group. Without: the namespace under `Controllers` (`Admin` for
 * `App\Http\Controllers\Admin\UserController`), the first segment of the URI or of the name. A group equal to the
 * tag is no group.
 */
final readonly class BuiltInGroupStrategy implements GroupStrategy
{
    public const BY = ['controller', 'uri', 'name'];

    public function __construct(
        private string $by = 'controller',
        private ?string $pattern = null,
    ) {}

    public function group(string $tag, OperationContext $operation): ?string
    {
        $value = RouteValues::of($this->by, $operation);

        if ($value === null || $value === '') {
            return null;
        }

        $group = $this->pattern !== null
            ? RouteValues::match($this->pattern, $value)
            : match ($this->by) {
                'controller' => RouteValues::match('/\\\\Controllers\\\\([^\\\\]+)\\\\/', $value),
                'uri' => explode('/', trim($value, '/'))[0],
                default => explode('.', $value)[0],
            };

        return $group === null || $group === '' || $group === $tag ? null : $group;
    }
}
