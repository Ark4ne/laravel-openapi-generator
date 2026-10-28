<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers;

use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\Post;

final class PostController
{
    public function index(): void {}

    public function store(): void {}

    public function show(Post $post): void {}

    public function update(Post $post): void {}

    public function destroy(Post $post): void {}

    public function updateRequest(int $id): void {}

    public function updateNoRequest(int $id): void {}

    public function storeRequest(): void {}

    public function storeNoRequest(): void {}
}
