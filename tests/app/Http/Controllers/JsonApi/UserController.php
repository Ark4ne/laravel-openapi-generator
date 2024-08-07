<?php

namespace Test\app\Http\Controllers\JsonApi;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Test\app\Http\Controllers\AsApiController;
use Test\app\Http\JsonApiResources\UserResource;
use Test\app\Http\Requests\UserRequest;
use Test\app\Http\Requests\UserStoreRequest;
use Test\app\Http\Requests\UserUpdateRequest;
use Test\app\Models\User;

class UserController extends Controller
{
    use AsApiController {
        AsApiController::index as apiIndex;
        AsApiController::show as apiShow;
    }

    protected function getModelClass(): string
    {
        return User::class;
    }

    protected function getResourceClass(): string
    {
        return UserResource::class;
    }

    /**
     * @param \Test\app\Http\Requests\UserRequest $request
     *
     * @return JsonApiCollection<UserResource>
     */
    public function index(UserRequest $request): JsonApiCollection
    {
        return $this->apiIndex($request);
    }

    /**
     * @param \Test\app\Http\Requests\UserRequest $request
     * @param string $id
     *
     * @return \Test\app\Http\JsonApiResources\UserResource
     */
    public function show(UserRequest $request, string $id): UserResource
    {
        return $this->apiShow($request, $id);
    }

    /**
     * @param string $id
     * @return UserJsonApiResource
     */
    public function updateNoRequest(string $id): UserResource
    {
        return $this->apiShow(\request(), $id);
    }

    /**
     * @param Request $request
     * @param string $id
     * @return UserResource
     */
    public function updateRequest(Request $request, string $id): UserResource
    {
        return $this->apiShow($request, $id);
    }

    /**
     * @param UserUpdateRequest $request
     * @param string $id
     * @return UserResource
     */
    public function update(UserUpdateRequest $request, string $id): UserResource
    {
        return $this->apiShow($request, $id);
    }

    /**
     * @param string $id
     * @return UserResource
     */
    public function storeNoRequest(string $id): UserResource
    {
        return $this->apiShow(\request(), $id);
    }

    /**
     * @param Request $request
     * @param string $id
     * @return UserResource
     */
    public function storeRequest(Request $request, string $id): UserResource
    {
        return $this->apiShow($request, $id);
    }

    /**
     * @param UserStoreRequest $request
     * @param string $id
     * @return UserResource
     */
    public function store(UserStoreRequest $request, string $id): UserResource
    {
        return $this->apiShow($request, $id);
    }

    /**
     * @param User $user
     * @oa-response-status 204
     * @return Response
     */
    public function destroy(User $user): Response
    {
        $user->delete();
        return response()->noContent();
    }
}
