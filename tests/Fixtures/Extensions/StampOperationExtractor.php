<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Extensions;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Operations\OperationExtractor;

/**
 * Stamps every operation with `x-extracted`, from the stamp its container gives.
 */
final readonly class StampOperationExtractor implements OperationExtractor
{
    public function __construct(
        private Stamp $stamp,
    ) {}

    public function priority(): int
    {
        return 1000;
    }

    public function supports(OperationContext $operation): bool
    {
        return true;
    }

    public function extract(OperationContext $operation, OperationDescription $description, GenerationContext $context): OperationDescription
    {
        return $description->x('extracted', $this->stamp->value);
    }
}
