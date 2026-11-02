<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * A JSON:API resource related to a native resource of unknown type.
 */
final class UntypedNativeHolderResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'holder';
    }

    protected function toRelationships(Request $request): iterable
    {
        return ['owner' => $this->one(NativeUntypedResource::class)];
    }
}
