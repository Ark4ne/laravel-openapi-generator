<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApi\Context\GenerationContext;
use Illuminate\Http\Request;

/**
 * An integration that prepares the requests of a generation and describes its document. Like every integration, it is
 * used only when available. Replaced by `DocumentIntegration` (RFC *Integration settings per document*, EI2).
 */
interface VariantIntegration extends Integration
{
    /**
     * Prepares a request the generator builds for the application: the sample request and every form request whose
     * rules are read.
     */
    public function prepareSampleRequest(Request $request, GenerationContext $context): void;

    /**
     * A markdown text appended to `info.description` of the document, `null` for none.
     */
    public function describe(GenerationContext $context): ?string;
}
