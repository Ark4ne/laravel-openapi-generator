<?php

namespace Test\app\Http\Controllers;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Test\app\Http\JsonApiResources\UserResource as UserJsonApiResource;
use Test\app\Http\Requests\UserRequest;
use Test\app\Http\Requests\UserStoreRequest;
use Test\app\Http\Requests\UserUpdateRequest;
use Test\app\Http\Resources\UserResource;
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

    protected function getJsonApiResourceClass(): string
    {
        return UserJsonApiResource::class;
    }

    /**
     * @param \Test\app\Http\Requests\UserRequest $request
     *
     * @return JsonApiCollection<UserJsonApiResource>
     */
    public function index(UserRequest $request): JsonApiCollection
    {
        return $this->apiIndex($request);
    }

    /**
     * @param \Test\app\Http\Requests\UserRequest $request
     * @param string $id
     *
     * @return \Test\app\Http\Resources\UserResource|\Test\app\Http\JsonApiResources\UserResource
     */
    public function show(UserRequest $request, string $id): UserResource|UserJsonApiResource
    {
        return $this->apiShow($request, $id);
    }

    /**
     * @param string $id
     * @return UserResource|UserJsonApiResource
     */
    public function updateNoRequest(string $id): UserResource|UserJsonApiResource
    {
        return $this->apiShow(\request(), $id);
    }

    /**
     * @param Request $request
     * @param string $id
     * @return UserResource|UserJsonApiResource
     */
    public function updateRequest(Request $request, string $id): UserResource|UserJsonApiResource
    {
        return $this->apiShow($request, $id);
    }

    /**
     * @param UserUpdateRequest $request
     * @param string $id
     * @return UserResource|UserJsonApiResource
     */
    public function update(UserUpdateRequest $request, string $id): UserResource|UserJsonApiResource
    {
        return $this->apiShow($request, $id);
    }

    /**
     * @param string $id
     * @return UserResource|UserJsonApiResource
     */
    public function storeNoRequest(string $id): UserResource|UserJsonApiResource
    {
        return $this->apiShow(\request(), $id);
    }

    /**
     * @param Request $request
     * @param string $id
     * @return UserResource|UserJsonApiResource
     */
    public function storeRequest(Request $request, string $id): UserResource|UserJsonApiResource
    {
        return $this->apiShow($request, $id);
    }

    /**
     * @param UserStoreRequest $request
     * @param string $id
     * @return UserResource|UserJsonApiResource
     */
    public function store(UserStoreRequest $request, string $id): UserResource|UserJsonApiResource
    {
        return $this->apiShow($request, $id);
    }
}
