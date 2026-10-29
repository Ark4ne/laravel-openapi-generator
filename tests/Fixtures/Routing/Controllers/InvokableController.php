<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers;

final class InvokableController
{
    public function __invoke(): void {}
}
