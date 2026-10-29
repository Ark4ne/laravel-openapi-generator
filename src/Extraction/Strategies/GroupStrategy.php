<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Strategies;

use Ark4ne\OpenApi\Extraction\OperationContext;

/**
 * Groups the tags of an operation under a parent tag (`groups` of a document configuration).
 */
interface GroupStrategy
{
    public function group(string $tag, OperationContext $operation): ?string;
}
