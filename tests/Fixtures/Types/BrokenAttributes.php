<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types;

use Ark4ne\OpenApi\Attributes\Operation;
use Ark4ne\OpenApi\Attributes\Tag;

/**
 * A controller with an attribute that cannot be built (unknown argument).
 */
#[Tag('valid')]
#[Operation(operationId: 'kept')]
final class BrokenAttributes
{
    /**
     * Index.
     */
    #[Operation(sumary: 'Typo')]
    public function index(): void {}
}
