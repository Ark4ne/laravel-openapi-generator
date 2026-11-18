<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Comment;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post;
use Illuminate\Http\Request;

/**
 * A polymorphic relationship, a relationship to a native resource with links declared by a closure, and one to a
 * collection with meta and identifier meta.
 *
 * @extends JsonApiResource<Post>
 */
#[JsonApiType('polymorphics')]
final class PolymorphicResource extends JsonApiResource
{
    protected function toRelationships(Request $request): iterable
    {
        return [
            'subject' => $this->morph([Post::class => PostResource::class, Comment::class => CommentResource::class]),
            'author' => $this->one(NativeAuthorResource::class)->links(static fn(): array => []),
            'tags' => $this->many(PostCollection::class, 'posts')
                ->links(self: static fn(): string => 'https://api.example.com/tags')
                ->meta(static fn(): array => ['count' => 0])
                ->identifierMeta(static fn(): array => ['weight' => 1]),
        ];
    }
}
