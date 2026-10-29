<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Resources;

use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\User;
use Illuminate\Http\Request;

/**
 * The described notation: descriptors named by their key or by their attribute, a struct, relations with links.
 *
 * @extends JsonApiResource<User>
 */
final class UserResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'user';
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toAttributes(Request $request): array
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

    /**
     * @return array<array-key, mixed>
     */
    protected function toResourceMeta(Request $request): array
    {
        return [
            'created_at' => $this->date(),
            $this->date('updated_at')->format('Y-m-d'),
        ];
    }

    /**
     * @return array<array-key, Relation<mixed>>
     */
    protected function toRelationships(Request $request): array
    {
        return [
            'posts' => $this->many(PostResource::class)->links(fn(): array => [
                'self' => "https://api.example.com/user/{$this->resource->id}/relationships/posts",
                'related' => "https://api.example.com/user/{$this->resource->id}/posts",
            ]),
            'comments' => $this->many(CommentResource::class)->whenLoaded(),
        ];
    }
}
