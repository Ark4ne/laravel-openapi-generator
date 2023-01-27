<?php

namespace Test\app\Http\JsonApiResources;

use Ark4ne\JsonApi\Resources\Concerns\ConditionallyLoadsAttributes;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @extends JsonApiResource<\Test\app\Models\Post>
 */
class PostResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'post';
    }

    protected function toAttributes(Request $request): iterable
    {
        return [
            'title' => $this->resource->title,
            'content' => $this->resource->content,
        ];
    }


    protected function toResourceMeta(Request $request): ?iterable
    {
        return [
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            $this->one(UserResource::class, 'user')->links(fn() => [
                'self' => "https://api.example.com/posts/{$this->resource->id}/relationships/user",
            ]),
            $this->many(CommentResource::class, 'comments')->links(fn() => [
                'self' => "https://api.example.com/posts/{$this->resource->id}/relationships/comments",
                'related' => "https://api.example.com/posts/{$this->resource->id}/comments",
            ]),
        ];
    }
}
