<?php

namespace Test\app\Http\Controllers\JsonApi;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Test\app\Http\Controllers\AsApiController;
use Test\app\Http\JsonApiResources\CommentResource;
use Test\app\Http\Requests\CommentRequest;
use Test\app\Models\Comment;

class CommentController extends Controller
{
    use AsApiController {
        AsApiController::index as apiIndex;
        AsApiController::show as apiShow;
    }

    protected function getModelClass(): string
    {
        return Comment::class;
    }

    protected function getResourceClass(): string
    {
        return CommentResource::class;
    }

    /**
     * @param \Test\app\Http\Requests\CommentRequest $request
     *
     * @return \Ark4ne\JsonApi\Resources\JsonApiCollection<CommentResource>
     */
    public function index(CommentRequest $request): JsonApiCollection
    {
        return $this->apiIndex($request);
    }

    public function show(CommentRequest $request, string $id): CommentResource
    {
        return $this->apiShow($request, $id);
    }

    /**
     * @param Comment $comment
     * @oa-response-status 204
     * @return Response
     */
    public function destroy(Comment $comment) : Response
    {
        $comment->delete();
        return response()->noContent();
    }
}
