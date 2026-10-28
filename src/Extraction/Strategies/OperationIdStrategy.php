<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Strategies;

use Ark4ne\OpenApi\Extraction\OperationContext;

/**
 * Proposes an operationId; a pass makes them unique across the document.
 */
interface OperationIdStrategy
{
    public function operationId(OperationContext $operation): ?string;
}
