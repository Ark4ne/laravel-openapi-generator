<?php

namespace Test\app\Http\JsonApiResources;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @extends JsonApiResource<\Test\app\Models\User>
 */
class UserResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'user';
    }

    protected function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->string(),
            $this->string('email')
        ];
    }

    protected function toResourceMeta(Request $request): ?iterable
    {
        return [
            'created_at' => $this->date(),
            $this->date('updated_at'),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'posts' => $this->many(PostResource::class)->links(fn() => [
                'self' => "https://api.example.com/user/{$this->resource->id}/relationships/posts",
                'related' => "https://api.example.com/user/{$this->resource->id}/posts",
            ]),
            'comments' => $this->many(CommentResource::class)->links(fn() => [
                'self' => "https://api.example.com/user/{$this->resource->id}/relationships/comments",
                'related' => "https://api.example.com/user/{$this->resource->id}/comments",
            ])->whenLoaded(),
        ];
    }
}
