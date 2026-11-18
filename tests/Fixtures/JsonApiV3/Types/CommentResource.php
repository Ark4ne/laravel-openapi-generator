<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types;

use Ark4ne\ApiRevisions\Schema\Schema;
use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Comment;
use Illuminate\Http\Request;

/**
 * A comment: a relationship back to its post, and one to itself (`parent`).
 *
 * @extends JsonApiResource<Comment>
 */
#[JsonApiType('comments')]
final class CommentResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return ['content' => Schema::string()];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'post' => $this->one(PostResource::class),
            'parent' => $this->one(self::class, static fn(): null => null),
        ];
    }
}
