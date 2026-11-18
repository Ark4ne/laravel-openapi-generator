<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * Two related resources of the same type.
 *
 * @extends JsonApiResource<\Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post>
 */
#[JsonApiType('twins')]
final class TwinsResource extends JsonApiResource
{
    protected function toRelationships(Request $request): iterable
    {
        return [
            'first' => $this->one(TwinAResource::class),
            'second' => $this->one(TwinBResource::class),
        ];
    }
}
