<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Resources;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Comment;
use Illuminate\Http\Request;

/**
 * The type of the class name (`comment`), relationships rendered when included.
 *
 * @extends JsonApiResource<Comment>
 */
final class CommentResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            $this->string('content'),
        ];
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'user' => $this->one(UserResource::class)->whenIncluded(),
            'post' => $this->one(PostResource::class)->whenIncluded(),
        ];
    }
}
