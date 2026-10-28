<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Extensions;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Generation\Middleware;
use Ark4ne\OpenApi\Generation\MiddlewareMapper;

/**
 * `tenant`: `x-tenant` with the stamp its container gives.
 */
final readonly class StampedMiddlewareMapper implements MiddlewareMapper
{
    public function __construct(
        private Stamp $stamp,
    ) {}

    public function handles(): array
    {
        return ['tenant'];
    }

    public function map(Middleware $middleware, OperationDescription $operation, DocumentDescription $document, GenerationContext $context): array
    {
        return [$operation->x('tenant', $this->stamp->value), $document];
    }
}
