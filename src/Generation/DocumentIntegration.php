<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApi\Context\GenerationContext;
use Illuminate\Http\Request;

/**
 * An integration that changes what it documents per document, from its settings in that document
 * (`documents.<name>.integrations.<key>`): it prepares the requests the generator builds, describes the document and
 * adds passes. Like every integration, it is used only when available; each method is called for every document, the
 * integration doing nothing without settings. One that throws is a warning diagnostic, the other integrations still
 * run. Its key is unique among the available integrations: a later one with the same key is left out, with a warning.
 */
interface DocumentIntegration extends Integration
{
    /**
     * The key of its settings in a document (`json_api`): read with `$context->document->integration($this->key())`.
     */
    public function key(): string;

    /**
     * Prepares a request the generator builds for the application (the sample request and every form request whose
     * rules are read), so that the application is read as a client of the document sees it: a header, an attribute,
     * the version of the package.
     */
    public function prepareRequest(Request $request, GenerationContext $context): void;

    /**
     * A markdown text appended to `info.description` of the document, `null` for none. Called inside the sampling
     * session: what it samples is rolled back.
     */
    public function describe(GenerationContext $context): ?string;

    /**
     * Passes for this document: they run after the built-in passes, before the application's, then the removal of the
     * unused component schemas and the cleanup. Called outside the sampling session, as every pass runs: it only builds
     * the passes. A pass that throws is a warning naming the integration, the document going on as before that pass.
     *
     * @return list<Pass>
     */
    public function passes(GenerationContext $context): array;
}
