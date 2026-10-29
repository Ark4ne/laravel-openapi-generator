<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Metadata\Controllers;

use Ark4ne\OpenApi\Attributes\Operation;
use Ark4ne\OpenApi\Attributes\Tag;
use Ark4ne\OpenApi\Tests\Fixtures\Metadata\Requests\StoreUserRequest;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\User;
use Ark4ne\OpenApi\Tests\Fixtures\Types\PostResource;
use Ark4ne\OpenApi\Tests\Fixtures\Types\UserResource;
use Deprecated;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

#[Tag('users', description: 'Users of the API')]
#[Operation(description: 'Default description of the controller')]
final class UserController
{
    /**
     * List the users.
     *
     * Sorted by name.
     */
    public function index(): void {}

    /**
     * Show a user.
     *
     * @deprecated use v2
     *
     * @throws ModelNotFoundException|RuntimeException
     */
    #[Operation(summary: 'Show one user', operationId: 'users.find', externalDocs: 'https://example.com/users', externalDocsDescription: 'Users guide')]
    public function show(User $user): UserResource|PostResource|null
    {
        return match (true) {
            $user->getKey() === null => throw new RuntimeException('Unsaved user'),
            $user->exists => new UserResource($user),
            $user->wasRecentlyCreated => new PostResource($user),
            default => null,
        };
    }

    #[Deprecated]
    public function legacy(): void {}

    /**
     * Store a user.
     */
    #[Operation(tags: ['accounts'], deprecated: false)]
    #[Tag('admin', parent: 'accounts', summary: 'Administration', kind: 'nav', externalDocs: 'https://example.com/admin')]
    public function store(StoreUserRequest $request): JsonResponse
    {
        return new JsonResponse();
    }

    /**
     * Restored.
     *
     * @deprecated
     */
    #[Operation(deprecated: false)]
    public function restore(): void {}
}
