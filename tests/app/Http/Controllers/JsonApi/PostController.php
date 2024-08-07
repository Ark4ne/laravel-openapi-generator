<?php

namespace Test\app\Http\Controllers\JsonApi;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Test\app\Http\Controllers\AsApiController;
use Test\app\Http\JsonApiResources\PostResource;
use Test\app\Http\Requests\PostRequest;
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

    /**
     * @param \Test\app\Http\Requests\PostRequest $request
     *
     * @return JsonApiCollection<PostResource>
     */
    public function index(PostRequest $request): JsonApiCollection
    {
        return $this->apiIndex($request);
    }

    public function show(PostRequest $request, string $id): PostResource
    {
        return $this->apiShow($request, $id);
    }

    /**
     * @param Post $post
     * @oa-response-status 204
     * @return Response
     */
    public function destroy(Post $post): Response
    {
        $post->delete();
        return response()->noContent();
    }
}
