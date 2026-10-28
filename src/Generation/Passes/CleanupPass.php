<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Passes;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Generation\Pass;

/**
 * The last pass: tags of an operation written once, a `default` response for an operation without any (OpenAPI 3.0
 * requires one).
 */
final readonly class CleanupPass implements Pass
{
    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        $clean = static function (OperationDescription $operation): OperationDescription {
            $operation = $operation->tags(...array_values(array_unique($operation->tags)));

            return $operation->responses === [] ? $operation->responses(ResponseDescription::create('default')->description('Response')) : $operation;
        };

        return $document->operations(...array_map($clean, $document->operations))->webhooks(...array_map($clean, $document->webhooks));
    }
}
