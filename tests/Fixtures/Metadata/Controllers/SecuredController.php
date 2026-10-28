<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Metadata\Controllers;

use Ark4ne\OpenApi\Attributes\Security;

#[Security('sanctum')]
final class SecuredController
{
    public function index(): void {}

    #[Security('passport', ['read'])]
    #[Security('apiKey')]
    public function show(): void {}
}
