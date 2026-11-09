<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Context;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Configuration\Variant;
use Closure;
use Illuminate\Http\Request;

/**
 * State of the generation of one document in one language and one variant, passed explicitly to every extractor, sampler and pass.
 *
 * It replaces the static state of v0.6 (Config::version(), ArrayCache, Logger, Component). It is not a service
 * container: dependencies arrive through the constructors.
 */
final readonly class GenerationContext
{
    /**
     * @param Variant|null $variant the variant generated, `null` for the head or a document without variants
     * @param list<Closure(Request, GenerationContext): void> $requestPreparers what prepares every sample request, in
     *        order (the `prepareSampleRequest()` of the available variant integrations)
     */
    public function __construct(
        public Configuration $configuration,
        public DocumentConfiguration $document,
        public Diagnostics $diagnostics = new Diagnostics(),
        public ComponentRegistry $components = new ComponentRegistry(),
        public ?string $locale = null,
        public ?Variant $variant = null,
        public array $requestPreparers = [],
    ) {}

    /**
     * A context for the given document, in that locale and that variant, with its own diagnostics and components.
     *
     * @param list<Closure(Request, GenerationContext): void> $requestPreparers
     */
    public static function for(Configuration $configuration, DocumentConfiguration $document, ?string $locale = null, ?Variant $variant = null, array $requestPreparers = []): self
    {
        $diagnostics = new Diagnostics();

        return new self($configuration, $document, $diagnostics, new ComponentRegistry($diagnostics), $locale, $variant, $requestPreparers);
    }

    /**
     * The name of the generation in the variant dimension: that of the variant, that of the head when the document has
     * variants, `null` for a document without variants.
     */
    public function variantName(): ?string
    {
        return $this->document->variants?->nameOf($this->variant);
    }

    /**
     * A new `GET /` request on which the application renders its samples (resources, responsables), prepared as
     * `prepareRequest()` does. The only place of `src/` that creates a plain request.
     */
    public function sampleRequest(): Request
    {
        return $this->prepareRequest(Request::create('/'));
    }

    /**
     * Prepares a request the generator builds for the application (a sample request, the form request whose rules are
     * read): the variant header when one is configured and a variant is generated (not for the head), then every
     * request preparer, in order.
     */
    public function prepareRequest(Request $request): Request
    {
        $header = $this->document->variants?->header;

        if ($header !== null && $this->variant !== null) {
            $request->headers->set($header, $this->variant->name);
        }

        foreach ($this->requestPreparers as $prepare) {
            $prepare($request, $this);
        }

        return $request;
    }
}
