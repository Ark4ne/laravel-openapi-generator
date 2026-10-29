<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types;

use Ark4ne\OpenApi\Tests\Fixtures\Types\Concerns\ListsPosts;

/**
 * Uses a trait whose PHPDoc imports a class this file does not import.
 */
final class UsesTrait
{
    use ListsPosts;
}
