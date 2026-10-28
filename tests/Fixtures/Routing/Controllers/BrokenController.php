<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers;

use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Broken;
use RuntimeException;

/**
 * A controller the container cannot build. `getMiddleware()` makes Laravel build it to read its middlewares.
 */
final class BrokenController
{
    public function __construct()
    {
        throw new RuntimeException('not buildable');
    }

    /**
     * @return list<array{middleware: string, options: array<string, mixed>}>
     */
    public function getMiddleware(): array
    {
        return [];
    }

    public function show(Broken $broken): void {}
}
