<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Extensions;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Generation\Middleware;
use Ark4ne\OpenApi\Generation\MiddlewareMapper;

/**
 * `tenant`: the operation carries `x-tenant`.
 */
final readonly class TenantMiddlewareMapper implements MiddlewareMapper
{
    public function handles(): array
    {
        return ['tenant'];
    }

    public function map(Middleware $middleware, OperationDescription $operation, DocumentDescription $document, GenerationContext $context): array
    {
        return [$operation->x('tenant', true), $document];
    }
}
