<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * A resource of Laravel's native JSON:API whose type is read on its model: unknown without one.
 */
final class NativeUntypedResource extends JsonApiResource
{
    public function toType(Request $request): string
    {
        return $this->resource->kind;
    }
}
