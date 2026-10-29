<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Resources;

use Ark4ne\JsonApi\Descriptors\Relations\Relation;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Comment;
use Illuminate\Http\Request;

/**
 * The type of the class name (`comment`), relations included on demand.
 *
 * @extends JsonApiResource<Comment>
 */
final class CommentResource extends JsonApiResource
{
    /**
     * @return array<array-key, mixed>
     */
    public function toAttributes(Request $request): array
    {
        return [
            $this->string('content'),
        ];
    }

    /**
     * @return array<array-key, Relation<mixed>>
     */
    protected function toRelationships(Request $request): array
    {
        return [
            'user' => $this->one(UserResource::class)->whenIncluded(),
            'post' => $this->one(PostResource::class)->whenIncluded(),
        ];
    }
}
