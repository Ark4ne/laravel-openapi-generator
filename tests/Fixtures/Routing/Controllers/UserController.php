<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers;

use Ark4ne\OpenApi\Attributes\Exclude;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\User;

final class UserController
{
    public function index(): void {}

    public function store(): void {}

    public function show(User $user): void {}

    public function update(int $id): void {}

    public function destroy(User $user): void {}

    public function updateRequest(int $id): void {}

    public function updateNoRequest(int $id): void {}

    public function storeRequest(): void {}

    public function storeNoRequest(): void {}

    #[Exclude]
    public function hidden(): void {}
}
