<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction;

use Ark4ne\OpenApi\Discovery\RouteDefinition;
use Ark4ne\OpenApi\Types\Type;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use ReflectionFunctionAbstract;

/**
 * What the extractors know about the operation they describe: its route, its method and its resolved action.
 */
final readonly class OperationContext
{
    /**
     * @param ReflectionFunctionAbstract|null $action controller method or closure; `null` when it cannot be resolved
     * @param Type|null $requestType type of the request parameter of the action
     * @param Type|null $returnType declared return type of the action (native, then PHPDoc)
     */
    public function __construct(
        public RouteDefinition $route,
        public HttpMethod|string $method,
        public string $path,
        public ?ReflectionFunctionAbstract $action = null,
        public ?Type $requestType = null,
        public ?Type $returnType = null,
    ) {}
}
