<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Variants;

/**
 * A revision whose value is not a file name.
 */
enum SlashedRevision: string
{
    case January = '2024/01';
}
