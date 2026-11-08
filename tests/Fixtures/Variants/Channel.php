<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Variants;

/**
 * Release channels: a pure enum, its variants named by the cases.
 */
enum Channel
{
    case Alpha;
    case Beta;
}
