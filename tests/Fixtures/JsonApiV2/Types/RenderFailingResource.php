<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A JSON:API resource whose closure throws when rendered on a sample of its model.
 *
 * @extends JsonApiResource<Post>
 */
final class RenderFailingResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'title' => $this->string(),
            'boom' => static fn(): never => throw new RuntimeException('exploded'),
        ];
    }
}
