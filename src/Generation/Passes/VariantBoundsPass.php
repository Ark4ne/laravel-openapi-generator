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
 * operations without `until`. Without `variants`, the document is unchanged.
 *
 * The generator already skips the routes whose bounds, once their operation is extracted, do not cover the variant:
 * their requests and responses are never read at it. This pass removes those bounded later (an integration, an
 * application's operation extractor) and reports, as warnings, each bound that names no variant of the document (it
 * bounds nothing: the operation is kept) and the bounds whose `since` is newer than their `until` (left out).
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
                $context->diagnostics->warning(Stage::Pass, "the bound {$name} is not a variant of the document, ignored", $operation->route);
            }

            if ($bounds->inverted($variants)) {
                $context->diagnostics->warning(Stage::Pass, "the bounds since {$bounds->since} until {$bounds->until} cover no variant, operation left out", $operation->route);

                return false;
            }

            return $bounds->covers($context->variant, $variants);
        };

        return $document
            ->operations(...array_values(array_filter($document->operations, $covered)))
            ->webhooks(...array_values(array_filter($document->webhooks, $covered)));
    }
}
