<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Context;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Configuration\Variant;

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
     */
    public function __construct(
        public Configuration $configuration,
        public DocumentConfiguration $document,
        public Diagnostics $diagnostics = new Diagnostics(),
        public ComponentRegistry $components = new ComponentRegistry(),
        public ?string $locale = null,
        public ?Variant $variant = null,
    ) {}

    /**
     * A context for the given document, in that locale and that variant, with its own diagnostics and components.
     */
    public static function for(Configuration $configuration, DocumentConfiguration $document, ?string $locale = null, ?Variant $variant = null): self
    {
        $diagnostics = new Diagnostics();

        return new self($configuration, $document, $diagnostics, new ComponentRegistry($diagnostics), $locale, $variant);
    }

    /**
     * The name of the generation in the variant dimension: that of the variant, that of the head when the document has
     * variants, `null` for a document without variants.
     */
    public function variantName(): ?string
    {
        return $this->document->variants?->nameOf($this->variant);
    }
}
