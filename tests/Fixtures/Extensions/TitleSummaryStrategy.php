<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Extensions;

use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Strategies\SummaryStrategy;

/**
 * A summary from the stamp its container gives.
 */
final readonly class TitleSummaryStrategy implements SummaryStrategy
{
    public function __construct(
        private Stamp $stamp,
    ) {}

    public function summary(OperationContext $operation): string
    {
        return "Summary {$this->stamp->value}";
    }
}
