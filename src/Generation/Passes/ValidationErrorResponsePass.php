<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Passes;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Generation\ErrorBodies;
use Ark4ne\OpenApi\Generation\Pass;
use Ark4ne\OpenApi\Generation\SharedResponses;

/**
 * 422 on every operation whose request has validation rules: the shared `ValidationFailed` response.
 */
final readonly class ValidationErrorResponsePass implements Pass
{
    public function __construct(
        private ErrorBodies $errors,
    ) {}

    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        $operations = [];

        foreach ($document->operations as $operation) {
            if ($operation->request->validated) {
                [$operation, $document] = SharedResponses::add($operation, $document, 'ValidationFailed', 422, 'Validation failed', $this->errors->validation($context), mediaType: $this->errors->mediaType());
            }

            $operations[] = $operation;
        }

        return $document->operations(...$operations);
    }
}
