<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApi\Context\GenerationContext;
use Illuminate\Http\Request;

/**
 * An integration that gives the variant of a document a meaning (`documents.<name>.variants`): it prepares the sample
 * requests of the generation and describes the variant. Like every integration, it is used only when available.
 */
interface VariantIntegration extends Integration
{
    /**
     * Prepares a sample request of the generation, which already carries the variant header when one is configured:
     * the application then renders the samples as a client of that variant sees them. Called for every sample request,
     * of the head and of a document without variants too (`$context->variant` is then `null`).
     */
    public function prepareSampleRequest(Request $request, GenerationContext $context): void;

    /**
     * A markdown text appended to `info.description` of the document (what the variant changes), `null` for none.
     */
    public function describe(GenerationContext $context): ?string;
}
