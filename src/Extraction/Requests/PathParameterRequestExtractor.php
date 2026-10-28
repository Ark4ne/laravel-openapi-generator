<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Requests;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\RequestDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;

/**
 * The path parameters of the route, typed by the discovery (constraints, bindings).
 */
final readonly class PathParameterRequestExtractor implements RequestExtractor
{
    public const PRIORITY = 0;

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(OperationContext $operation): bool
    {
        return $operation->route->parameters !== [];
    }

    public function extract(OperationContext $operation, RequestDescription $request, GenerationContext $context): RequestDescription
    {
        return $request->parameters(...FieldPlacer::merge($operation->route->parameters, $request->parameters));
    }
}
