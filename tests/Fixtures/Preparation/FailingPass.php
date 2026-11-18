<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Preparation;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Generation\Pass;
use RuntimeException;

/**
 * Adds a note, then throws: what it did must not reach the document.
 */
final readonly class FailingPass implements Pass
{
    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        $document->addNotes('half done');

        throw new RuntimeException('settings of an unexpected shape');
    }
}
