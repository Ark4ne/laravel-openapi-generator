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
     * The aliases it handles (`auth`, `throttle`…); the declared classes are read as their alias.
     *
     * @return list<string>
     */
    public function handles(): array;

    /**
     * Returns the operation and the document, which receives the shared components (security schemes, responses).
     *
     * @return array{OperationDescription, DocumentDescription}
     */
    public function map(Middleware $middleware, OperationDescription $operation, DocumentDescription $document, GenerationContext $context): array;
}
