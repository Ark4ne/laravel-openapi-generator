<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Comment;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Resources\CommentResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Resources\PostResource;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Status;
use Illuminate\Http\Request;

/**
 * The value types a definition gives, a polymorphic relationship and a relationship to a native resource.
 */
final class ShowcaseResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'showcase';
    }

    public function toAttributes(Request $request): iterable
    {
        return [
            'code' => $this->uuid(),
            'state' => $this->enum()->of(Status::class),
            'ranks' => $this->arrayOf($this->integer()->nullable(false)),
            'anything' => $this->array(),
            'opened' => $this->datetime()->nullable(false),
            'clock' => $this->time(),
            'secret' => $this->string()->whenInFields(),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'subject' => $this->morph([Post::class => PostResource::class, Comment::class => CommentResource::class]),
            'author' => $this->one(NativeAuthorResource::class)->links(static fn(): array => []),
        ];
    }
}
