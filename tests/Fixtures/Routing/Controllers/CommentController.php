<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers;

final class CommentController
{
    public function index(): void {}

    public function show(string $comment): void {}

    public function destroy(string $comment): void {}
}
