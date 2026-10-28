<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;

/**
 * What a middleware adds to an operation (security, headers, error responses). Configured per middleware in
 * `middlewares` and applied by the middleware pass; replaces the transformers of v0.6.
 */
interface MiddlewareMapper
{
    /**
     * @param string $middleware as declared on the route (`auth:sanctum`, `throttle:60,1`)
     */
    public function supports(string $middleware): bool;

    /**
     * Returns the operation and the document, which receives the shared components (security schemes, responses).
     *
     * @return array{OperationDescription, DocumentDescription}
     */
    public function map(string $middleware, OperationDescription $operation, DocumentDescription $document, GenerationContext $context): array;
}
