<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Controllers;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Resources\PostResource;

final class PostController
{
    /**
     * List the posts.
     *
     * @return JsonApiCollection<PostResource>
     */
    public function index(): JsonApiCollection
    {
        return PostResource::collection(Post::query()->get());
    }

    /**
     * Show one post.
     */
    public function show(Post $post): PostResource
    {
        return new PostResource($post);
    }
}
