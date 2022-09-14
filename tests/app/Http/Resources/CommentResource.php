<?php

namespace Test\app\Http\Resources;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @extends JsonResource<\Test\app\Models\Comment>
 */
class CommentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'type' => 'comment',
            'attributes' => [
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
            'user' => $this->when(true, UserResource::relationship(fn() => $this->resource->user)
                ->withLinks(fn() => [
                    'self' => "https://api.example.com/comment/{$this->resource->id}/relationships/user",
                    'related' => "https://api.example.com/comment/{$this->resource->id}/user",
                ])
                ->whenIncluded()),
            'post' => PostResource::relationship(fn() => $this->resource->post)
                ->withLinks(fn() => [
                    'self' => "https://api.example.com/comment/{$this->resource->id}/relationships/post",
                    'related' => "https://api.example.com/comment/{$this->resource->id}/post",
                ])
                ->whenIncluded(),
        ];
    }
}
