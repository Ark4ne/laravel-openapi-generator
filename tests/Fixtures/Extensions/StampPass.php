<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Extensions;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Generation\Pass;

/**
 * Stamps every operation with `x-pass`.
 */
final readonly class StampPass implements Pass
{
    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        return $document->operations(...array_map(static fn(OperationDescription $operation): OperationDescription => $operation->x('pass', true), $document->operations));
    }
}
