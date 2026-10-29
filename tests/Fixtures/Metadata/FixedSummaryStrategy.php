<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Metadata;

use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Strategies\SummaryStrategy;

final class FixedSummaryStrategy implements SummaryStrategy
{
    public function summary(OperationContext $operation): string
    {
        return 'Fixed';
    }
}
