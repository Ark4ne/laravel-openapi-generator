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
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * `signed`: the `signature` and `expires` query parameters of a signed URL, and the 403 of an invalid signature.
 */
final readonly class SignedMiddlewareMapper implements MiddlewareMapper
{
    public function __construct(
        private ErrorBodies $errors,
    ) {}

    public function handles(): array
    {
        return ['signed'];
    }

    public function map(Middleware $middleware, OperationDescription $operation, DocumentDescription $document, GenerationContext $context): array
    {
        $parameters = [
            ParameterDescription::query('signature', Schema::string())->required()->description('Signature of the URL.'),
            ParameterDescription::query('expires', Schema::integer())->description('Expiration of a temporary signed URL (Unix timestamp).'),
        ];
        $operation = $operation->request($operation->request->parameters(...FieldPlacer::merge($operation->request->parameters, $parameters)));

        return SharedResponses::add($operation, $document, 'Forbidden', 403, 'Forbidden', $this->errors->error($context), mediaType: $this->errors->mediaType());
    }
}
