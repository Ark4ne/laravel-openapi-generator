<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Resources;

use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post;
use Illuminate\Http\Request;

/**
 * Raw values, typed from a sample of the model; an enum known by the cast of the model.
 *
 * @extends JsonApiResource<Post>
 */
final class PostResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'post';
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            'title' => $this->resource->title,
            'content' => $this->resource->content,
            'status' => $this->enum(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function toLinks(Request $request): array
    {
        return ['self' => "https://api.example.com/posts/{$this->resource->id}"];
    }

    /**
     * @return array<array-key, Relation<mixed>>
     */
    protected function toRelationships(Request $request): array
    {
        return [
            $this->one(UserResource::class, 'user')->meta(static fn(): array => ['role' => 'author']),
            $this->many(CommentResource::class, 'comments'),
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function toMeta(Request $request): array
    {
        return ['version' => '1.0'];
    }
}
