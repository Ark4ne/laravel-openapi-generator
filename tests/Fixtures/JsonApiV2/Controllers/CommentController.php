<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Controllers;

use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Comment;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Resources\CommentResource;

final class CommentController
{
    /**
     * Show one comment.
     */
    public function show(Comment $comment): CommentResource
    {
        return new CommentResource($comment);
    }
}
