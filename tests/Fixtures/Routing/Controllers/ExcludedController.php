<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers;

use Ark4ne\OpenApi\Attributes\Exclude;

#[Exclude]
final class ExcludedController
{
    public function index(): void {}
}
