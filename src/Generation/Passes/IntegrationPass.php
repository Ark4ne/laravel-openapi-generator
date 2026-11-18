<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Passes;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Generation\Pass;
use Throwable;

/**
 * A pass a document integration adds, guarded: when it throws, a warning names the integration and the document goes
 * on as it was before that pass.
 */
final readonly class IntegrationPass implements Pass
{
    public function __construct(
        public string $integration,
        public Pass $pass,
    ) {}

    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        try {
            return $this->pass->process($document, $context);
        } catch (Throwable $error) {
            $class = $this->pass::class;
            $context->diagnostics->warning(Stage::Pass, "the pass {$class} of the integration {$this->integration} failed, left out: {$error->getMessage()}");

            return $document;
        }
    }
}
