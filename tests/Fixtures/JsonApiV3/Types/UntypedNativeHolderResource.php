<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types;

use Ark4ne\JsonApi\Resources\Attributes\JsonApiType;
use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * @extends JsonApiResource<\Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post>
 */
#[JsonApiType('holders')]
final class UntypedNativeHolderResource extends JsonApiResource
{
    protected function toRelationships(Request $request): iterable
    {
        return ['owner' => $this->one(NativeUntypedResource::class)];
    }
}
