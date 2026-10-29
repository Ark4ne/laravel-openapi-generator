<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Passes;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\SecurityDescription;
use Ark4ne\OpenApi\Generation\Middleware;
use Ark4ne\OpenApi\Generation\MiddlewareMappers;
use Ark4ne\OpenApi\Generation\Pass;
use Ark4ne\OpenApi\Generation\SecuritySchemes;

/**
 * The middlewares of each operation through their mappers (security, parameters, responses); the configured
 * schemes (`security_schemes`) replace the inferred ones of the same name, the default security (`security`) is
 * set on the document. A security declared on the operation (`#[Security]`) is kept.
 */
final readonly class MiddlewareSecurityPass implements Pass
{
    public function __construct(
        private MiddlewareMappers $mappers,
    ) {}

    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        $operations = [];

        foreach ($document->operations as $operation) {
            $declared = $operation->security;

            foreach ($operation->middlewares as $declaration) {
                $middleware = Middleware::parse($declaration);

                foreach ($this->mappers->for($middleware) as $mapper) {
                    [$operation, $document] = $mapper->map($middleware, $operation, $document, $context);
                }
            }

            $operations[] = $declared === null ? $operation : $operation->security(...$declared);
        }

        foreach ($context->document->securitySchemes as $name => $scheme) {
            $document = $document->securityScheme($name, SecuritySchemes::fromArray($name, $scheme));
        }

        $default = array_map(static fn(string $scheme): SecurityDescription => SecurityDescription::create([$scheme => []]), $context->document->security);

        return $document->operations(...$operations)->addSecurity(...$default);
    }
}
