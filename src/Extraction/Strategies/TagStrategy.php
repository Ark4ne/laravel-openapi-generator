<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Strategies;

use Ark4ne\OpenApi\Extraction\OperationContext;

/**
 * Tags an operation: controller, URI prefix, route name (`tags` of a document configuration).
 */
interface TagStrategy
{
    /**
     * @return list<string>
     */
    public function tags(OperationContext $operation): array;
}
