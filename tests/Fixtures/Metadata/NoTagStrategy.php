<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Metadata;

use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Strategies\TagStrategy;

/**
 * Gives no tag, so that a test sees the tags of the attributes alone.
 */
final class NoTagStrategy implements TagStrategy
{
    public function tags(OperationContext $operation): array
    {
        return [];
    }
}
