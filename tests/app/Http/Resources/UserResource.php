<?php

namespace Test\app\Http\Resources;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @extends JsonResource<\Test\app\Models\User>
 */
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'type' => 'user',
            'attributes' => [
                'name' => $this->resource->name,
                'email' => $this->resource->email,
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
            'posts' => PostResource::relationship(fn() => $this->resource->posts, fn() => [
                'self' => "https://api.example.com/user/{$this->resource->id}/relationships/posts",
                'related' => "https://api.example.com/user/{$this->resource->id}/posts",
            ])->asCollection(),
            'comments' => CommentResource::relationship(fn() => $this->whenLoaded('comments'), fn() => [
                'self' => "https://api.example.com/user/{$this->resource->id}/relationships/comments",
                'related' => "https://api.example.com/user/{$this->resource->id}/comments",
            ])->asCollection()
        ];
    }
}
