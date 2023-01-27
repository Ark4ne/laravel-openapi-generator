<?php

namespace Test\app\Http\Controllers;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controller;
use Test\app\Http\JsonApiResources\PostResource as PostJsonApiResource;
use Test\app\Http\Requests\PostRequest;
use Test\app\Http\Resources\PostResource;
use Test\app\Models\Post;

class PostController extends Controller
{
    use AsApiController {
        AsApiController::index as apiIndex;
        AsApiController::show as apiShow;
    }

    protected function getModelClass(): string
    {
        return Post::class;
    }

    protected function getResourceClass(): string
    {
        return PostResource::class;
    }

    protected function getJsonApiResourceClass(): string
    {
        return PostJsonApiResource::class;
    }

    /**
     * @param \Test\app\Http\Requests\PostRequest $request
     *
     * @return \Illuminate\Http\Resources\Json\ResourceCollection<PostResource>|\Ark4ne\JsonApi\Resources\JsonApiCollection<PostJsonApiResource>
     */
    public function index(PostRequest $request): ResourceCollection | JsonApiCollection
    {
        return $this->apiIndex($request);
    }

    public function show(PostRequest $request, string $id): PostResource | PostJsonApiResource
    {
        return $this->apiShow($request, $id);
    }
}
