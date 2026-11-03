<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Resources;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post;
use Illuminate\Http\Request;

/**
 * Closures without descriptor, typed from a sample of the model; an enum known by the cast of the model.
 *
 * @extends JsonApiResource<Post>
 */
final class PostResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'post';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => fn(): string => $this->resource->title,
            'content' => fn(): string => $this->resource->content,
            'status' => $this->enum(),
        ];
    }

    protected function toLinks(Request $request): iterable
    {
        return ['self' => "https://api.example.com/posts/{$this->resource->id}"];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(UserResource::class)->meta(static fn(): array => ['role' => 'author']),
            'comments' => $this->many(CommentResource::class),
        ];
    }

    protected function toDocumentMeta(Request $request): iterable
    {
        return ['version' => fn(): string => '1.0'];
    }
}
