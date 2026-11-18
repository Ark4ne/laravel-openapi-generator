<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post;
use Illuminate\Http\Request;

/**
 * A post: its comments, its document meta.
 *
 * @extends JsonApiResource<Post>
 */
#[JsonApiType('posts')]
final class PostResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => Schema::string(),
            'state' => Schema::enum(\Ark4ne\OpenApi\Tests\Fixtures\Routing\Enums\Status::class, 'status'),
            'computed' => $this->mixed(static fn(): int => 42),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return ['comments' => $this->many(CommentResource::class)];
    }

    protected function toDocumentMeta(Request $request): iterable
    {
        return ['generated' => Schema::bool(static fn(): bool => true)];
    }
}
