<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;

/**
 * The passes, in their explicit order: security → error responses → tags and groups → operationId → components →
 * cleanup. The default chain comes with E7.
 */
final class Passes
{
    /** @var list<Pass> */
    private readonly array $passes;

    public function __construct(Pass ...$passes)
    {
        $this->passes = array_values($passes);
    }

    /**
     * @return list<Pass> in order
     */
    public function all(): array
    {
        return $this->passes;
    }

    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        return $document;
    }
}
