<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * A JSON:API resource whose first relationship goes back to itself.
 */
final class NestedJsonApiResource extends JsonApiResource
{
    protected function toRelationships(Request $request): iterable
    {
        return [
            'parent' => $this->one(self::class),
            'tags' => $this->many(TagResource::class),
        ];
    }
}
