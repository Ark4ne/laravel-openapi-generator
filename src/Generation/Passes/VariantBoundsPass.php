<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Passes;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Generation\Pass;

/**
 * The first pass of a document with variants: the operations and the webhooks whose bounds do not cover the variant
 * generated are removed, before any pass reads them (tags, operationIds, error responses). The head keeps the
 * operations without `until`. Each bound that names no variant of the document is reported as an error and bounds
 * nothing: the operation is kept. Without `variants`, the document is unchanged.
 */
final readonly class VariantBoundsPass implements Pass
{
    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        $variants = $context->document->variants;

        if ($variants === null) {
            return $document;
        }

        $covered = static function (OperationDescription $operation) use ($variants, $context): bool {
            $bounds = $operation->bounds;

            if ($bounds === null) {
                return true;
            }

            foreach ($bounds->unknown($variants) as $name) {
                $context->diagnostics->error(Stage::Pass, "the bound {$name} is not a variant of the document, ignored", $operation->route);
            }

            return $bounds->covers($context->variant, $variants);
        };

        return $document
            ->operations(...array_values(array_filter($document->operations, $covered)))
            ->webhooks(...array_values(array_filter($document->webhooks, $covered)));
    }
}
