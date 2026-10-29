<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Middlewares;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\SecurityDescription;
use Ark4ne\OpenApi\Generation\Middleware;
use Ark4ne\OpenApi\Generation\MiddlewareMapper;
use Ark4ne\OpenApiWriter\Objects\SecurityScheme;

/**
 * `auth.basic`: HTTP basic authentication (`basic`).
 */
final readonly class BasicAuthMiddlewareMapper implements MiddlewareMapper
{
    public function handles(): array
    {
        return ['auth.basic'];
    }

    public function map(Middleware $middleware, OperationDescription $operation, DocumentDescription $document, GenerationContext $context): array
    {
        $document = isset($document->securitySchemes['basic']) ? $document : $document->securityScheme('basic', SecurityScheme::http('basic')->scheme('basic'));

        return [$operation->addSecurity(SecurityDescription::create(['basic' => []])), $document];
    }
}
