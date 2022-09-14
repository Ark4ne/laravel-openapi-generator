<?php

namespace Test\app\Http\Resources;

use Ark4ne\JsonApi\Resources\Concerns\ConditionallyLoadsAttributes;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @extends JsonResource<\Test\app\Models\Post>
 */
class PostResource extends JsonResource
{    public function toArray($request)
{
    return [
        'type' => 'post',
        'attributes' => [
            'title' => $this->resource->title,
            'content' => $this->resource->content,
        ],
        'meta' => [
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ]
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
