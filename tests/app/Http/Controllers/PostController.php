<?php

namespace Test\app\Http\Controllers;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controller;
use Test\app\Http\Requests\PostRequest;
use Test\app\Http\Requests\UserRequest;
use Test\app\Http\Resources\PostResource;
use Test\app\Http\Resources\UserResource;
use Test\app\Http\Schema\PostSchema;
use Test\app\Http\Schema\UserSchema;
use Test\app\Models\Post;
use Test\app\Models\User;

class PostController extends Controller
{
    use AsApiController {
        index as apiIndex;
        show as apiShow;
    }

    protected function getModelClass(): string
    {
        return Post::class;
    }

    protected function getResourceClass(): string
    {
        return PostResource::class;
    }

    public function index(PostRequest $request): ResourceCollection
    {
        return $this->apiIndex($request);
    }

    public function show(PostRequest $request, string $id): PostResource
    {
        return $this->apiShow($request, $id);
    }
}
