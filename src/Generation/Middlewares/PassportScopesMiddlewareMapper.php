<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Middlewares;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Generation\Middleware;
use Ark4ne\OpenApi\Generation\MiddlewareMapper;
use Ark4ne\OpenApiWriter\Objects\OAuthFlows;

/**
 * `scopes:` (all of them) and `scope:` (any of them) of Passport: the scopes of the `passport` requirement, one
 * requirement per scope for `scope:`; the scopes are declared in the flows of the `passport` scheme.
 */
final readonly class PassportScopesMiddlewareMapper implements MiddlewareMapper
{
    public function handles(): array
    {
        return ['scopes', 'scope'];
    }

    public function map(Middleware $middleware, OperationDescription $operation, DocumentDescription $document, GenerationContext $context): array
    {
        $alternatives = $middleware->name === 'scopes' ? [$middleware->parameters] : array_map(static fn(string $scope): array => [$scope], $middleware->parameters);

        $scheme = $document->securitySchemes['passport'] ?? null;

        if ($scheme !== null && $scheme->flows instanceof OAuthFlows) {
            $document = $document->securityScheme('passport', $scheme->flows(Scopes::declare($scheme->flows, $middleware->parameters)));
        }

        return [$operation->security(...Scopes::apply($operation->security ?? [], 'passport', $alternatives)), $document];
    }
}
