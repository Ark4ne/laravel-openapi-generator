<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;

/**
 * One cross-cutting concern applied to the whole document (security, error responses, tags, components…).
 */
interface Pass
{
    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription;
}
