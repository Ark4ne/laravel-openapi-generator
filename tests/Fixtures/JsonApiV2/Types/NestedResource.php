<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * A JSON:API resource whose first relationship goes back to itself.
 */
final class NestedResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'nested';
    }

    protected function toRelationships(Request $request): iterable
    {
        return [
            'parent' => $this->one(self::class),
            'tags' => $this->many(TagResource::class),
        ];
    }
}
