<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Preparation;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Generation\Pass;

/**
 * Appends its name to the notes of the document: the order of the notes is the order the passes ran in.
 */
final readonly class NotePass implements Pass
{
    public function __construct(private string $name) {}

    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        return $document->addNotes($this->name);
    }
}
