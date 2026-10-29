<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Strategies;

use Ark4ne\OpenApi\Extraction\OperationContext;

/**
 * Names an operation: PHPDoc summary, route name, URI, controller (`summary` of a document configuration).
 */
interface SummaryStrategy
{
    public function summary(OperationContext $operation): ?string;
}
