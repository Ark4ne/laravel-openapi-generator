<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Resources;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\User;
use Illuminate\Http\Request;

/**
 * The described notation: descriptors named by their key or by their attribute, a struct, relationships with links
 * declared by name.
 *
 * @extends JsonApiResource<User>
 */
final class UserResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'user';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            'name' => $this->string(),
            $this->string('email'),
            'profile' => $this->struct(fn(): array => [
                'name' => $this->string('name'),
                'initials' => $this->string(static fn(): string => 'A'),
                'verified' => $this->bool(static fn(): bool => true)->nullable(false),
                'hidden' => $this->mixed(static fn(): string => 'value')->when(false),
                'scores' => $this->struct(fn(): array => [
                    'rank' => $this->integer(static fn(): int => 1),
                    'ratio' => $this->float(static fn(): float => 0.5),
                ]),
            ]),
        ];
    }

    protected function toResourceMeta(Request $request): iterable
    {
        return [
            'created_at' => $this->datetime(),
            $this->date('updated_at'),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'posts' => $this->many(PostResource::class)->links(
                self: fn(): string => "https://api.example.com/user/{$this->resource->id}/relationships/posts",
                related: fn(): string => "https://api.example.com/user/{$this->resource->id}/posts",
            ),
            'comments' => $this->many(CommentResource::class)->whenLoaded(),
        ];
    }
}
