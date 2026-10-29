<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Controllers;

use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Comment;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Resources\CommentResource;

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
