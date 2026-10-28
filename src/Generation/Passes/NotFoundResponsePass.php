<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Passes;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Extraction\Exceptions\ErrorBodies;
use Ark4ne\OpenApi\Generation\Pass;
use Ark4ne\OpenApi\Generation\SharedResponses;
use Ark4ne\OpenApiWriter\Enums\ParameterLocation;

/**
 * 404 on every operation whose path binds a model or a backed enum.
 */
final readonly class NotFoundResponsePass implements Pass
{
    public function __construct(
        private ErrorBodies $errors,
    ) {}

    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        $operations = [];

        foreach ($document->operations as $operation) {
            foreach ($operation->request->parameters as $parameter) {
                if ($parameter->in === ParameterLocation::Path && $parameter->boundTo !== null) {
                    [$operation, $document] = SharedResponses::add($operation, $document, 'NotFound', 404, 'Not Found', $this->errors->error($context), mediaType: $this->errors->mediaType());
                }
            }

            $operations[] = $operation;
        }

        return $document->operations(...$operations);
    }
}
