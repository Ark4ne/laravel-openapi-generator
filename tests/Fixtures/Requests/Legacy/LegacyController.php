<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Requests\Legacy;

final class LegacyController
{
    public function users(UserRequest $request): void {}

    public function store(UserStoreRequest $request): void {}

    public function update(UserUpdateRequest $request, string $id): void {}

    public function posts(PostRequest $request): void {}

    public function comments(CommentRequest $request): void {}
}
