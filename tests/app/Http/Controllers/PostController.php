<?php

namespace Test\app\Http\Controllers;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
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

    /**
     * @param \Test\app\Http\Requests\PostRequest $request
     *
     * @return \Illuminate\Http\Resources\Json\ResourceCollection<PostResource>
     */
    public function index(PostRequest $request): ResourceCollection
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
