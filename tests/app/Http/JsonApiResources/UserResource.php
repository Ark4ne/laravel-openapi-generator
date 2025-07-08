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
            $this->string('email'),
            'struct-set' => $this->struct(fn () => [
                $this->string('name'),
                'email' => $this->resource->email,
                'casted' => $this->string(fn() => 'string'),
                $this->applyWhen(fn () => true, [
                    'with-apply-conditional-raw' => 'huge-data-set',
                ]),
                'closure' => fn() => 'closure',
                'missing' => $this->mixed(fn() => 'value')->when(false),
                'sub-struct' => $this->struct(fn () => [
                    'int' => $this->float(fn () => 200),
                    'float' => $this->float(fn () => 1.1),
                ]),
                'third-struct' => $this->struct(fn () => [
                    'int' => $this->float(fn () => 300),
                    'float' => $this->float(fn () => 3.1),
                ])->when(false),
            ]),
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
