<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Middlewares;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Generation\Middleware;
use Ark4ne\OpenApi\Generation\MiddlewareMapper;
use Ark4ne\OpenApiWriter\Enums\Version;

/**
 * `abilities:` (all of them) and `ability:` (any of them) of Sanctum: from 3.1, the scopes of the `sanctum`
 * requirement (3.1 allows them for any scheme); before, a sentence of the description.
 */
final readonly class SanctumAbilitiesMiddlewareMapper implements MiddlewareMapper
{
    public function handles(): array
    {
        return ['abilities', 'ability'];
    }

    public function map(Middleware $middleware, OperationDescription $operation, DocumentDescription $document, GenerationContext $context): array
    {
        $all = $middleware->name === 'abilities';

        if (! $context->document->version->isAtLeast(Version::V3_1_0)) {
            $sentence = sprintf($all ? 'Requires the abilities %s.' : 'Requires one of the abilities %s.', implode(', ', $middleware->parameters));
            $description = $operation->description === null ? $sentence : "{$operation->description}\n\n{$sentence}";

            return [$operation->description($description), $document];
        }

        $alternatives = $all ? [$middleware->parameters] : array_map(static fn(string $ability): array => [$ability], $middleware->parameters);

        return [$operation->security(...Scopes::apply($operation->security ?? [], 'sanctum', $alternatives)), $document];
    }
}
