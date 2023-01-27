<?php

namespace Test\app\Http\JsonApiResources;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @extends JsonApiResource<\Test\app\Models\Comment>
 */
class CommentResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'comment';
    }

    protected function toAttributes(Request $request): iterable
    {
        return [
            $this->string('content')
        ];
    }

    protected function toResourceMeta(Request $request): ?iterable
    {
        return [
            $this->date('created_at'),
            $this->date('updated_at'),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(UserResource::class)
                ->when(fn() => true)
                ->whenIncluded()
                ->links(fn() => [
                    'self' => "https://api.example.com/comment/{$this->resource->id}/relationships/user",
                    'related' => "https://api.example.com/comment/{$this->resource->id}/user",
                ]),
            'post' => $this->one(PostResource::class)
                ->whenIncluded()
                ->links(fn() => [
                    'self' => "https://api.example.com/comment/{$this->resource->id}/relationships/post",
                    'related' => "https://api.example.com/comment/{$this->resource->id}/post",
                ]),
        ];
    }
}
