<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * A JSON:API resource related to two resources of the same type.
 */
final class TwinsJsonApiResource extends JsonApiResource
{
    protected function toRelationships(Request $request): iterable
    {
        return [
            'first' => $this->one(TwinAResource::class),
            'second' => $this->one(TwinBResource::class),
        ];
    }
}
