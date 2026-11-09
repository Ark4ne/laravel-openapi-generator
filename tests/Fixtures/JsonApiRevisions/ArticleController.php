<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiRevisions;

use Ark4ne\JsonApi\Resources\JsonApiCollection;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\User;

final class ArticleController
{
    /**
     * List the articles.
     *
     * @return JsonApiCollection<ArticleResource>
     */
    public function index(ArticleIndexRequest $request): JsonApiCollection
    {
        return ArticleResource::collection(Post::query()->get());
    }

    /**
     * Show one article.
     */
    public function show(Post $post): ArticleResource
    {
        return new ArticleResource($post);
    }

    /**
     * Create an article.
     */
    public function store(ArticleRequest $request): ArticleResource
    {
        return new ArticleResource(new Post());
    }

    /**
     * Preview one article.
     */
    public function preview(PreviewRequest $request, Post $post): ArticleResource
    {
        return new ArticleResource($post);
    }

    /**
     * Show one member: absent before Labels.
     */
    public function member(User $user): MemberResource
    {
        return new MemberResource($user);
    }
}
