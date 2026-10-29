<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Middlewares;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Extraction\Exceptions\ErrorBodies;
use Ark4ne\OpenApi\Extraction\Requests\FieldPlacer;
use Ark4ne\OpenApi\Generation\Middleware;
use Ark4ne\OpenApi\Generation\MiddlewareMapper;
use Ark4ne\OpenApi\Generation\SharedResponses;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;

/**
 * `ValidateCsrfToken` (the `web` group): on the methods that change something, the `X-CSRF-TOKEN` header (or the
 * `_token` field) and the 419 of an expired token.
 */
final readonly class CsrfMiddlewareMapper implements MiddlewareMapper
{
    private const READING = [HttpMethod::Get, HttpMethod::Head, HttpMethod::Options];

    public function __construct(
        private ErrorBodies $errors,
    ) {}

    public function handles(): array
    {
        return ['csrf'];
    }

    public function map(Middleware $middleware, OperationDescription $operation, DocumentDescription $document, GenerationContext $context): array
    {
        if (in_array($operation->method, self::READING, true)) {
            return [$operation, $document];
        }

        $header = ParameterDescription::header('X-CSRF-TOKEN')->description('CSRF token, unless the body holds `_token`.');
        $operation = $operation->request($operation->request->parameters(...FieldPlacer::merge($operation->request->parameters, [$header])));

        return SharedResponses::add($operation, $document, 'PageExpired', 419, 'Page Expired', $this->errors->error($context), mediaType: $this->errors->mediaType());
    }
}
