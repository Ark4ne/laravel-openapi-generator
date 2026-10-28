<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Controllers;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\OpenApi\Attributes\Response;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\User;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Requests\UserIndexRequest;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Requests\UserStoreRequest;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Resources\UserResource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Response as HttpResponse;

final class UserController
{
    /**
     * List the users.
     *
     * @return JsonApiCollection<UserResource>
     */
    #[Response(200, resource: UserResource::class, paginator: LengthAwarePaginator::class)]
    public function index(UserIndexRequest $request): JsonApiCollection
    {
        return UserResource::collection(User::query()->paginate());
    }

    /**
     * Show one user.
     */
    public function show(UserIndexRequest $request, User $user): UserResource
    {
        return new UserResource($user);
    }

    /**
     * Create a user.
     */
    public function store(UserStoreRequest $request): UserResource
    {
        return new UserResource(User::query()->create($request->validated()));
    }

    /**
     * Delete a user.
     */
    #[Response(204)]
    public function destroy(User $user): HttpResponse
    {
        $user->delete();

        return new HttpResponse(status: 204);
    }
}
