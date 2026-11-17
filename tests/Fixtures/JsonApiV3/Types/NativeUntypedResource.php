<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use LogicException;

/**
 * A resource of Laravel's native JSON:API whose type needs its model.
 */
final class NativeUntypedResource extends JsonApiResource
{
    public function toType(Request $request): string
    {
        if (! $this->resource instanceof Model) {
            throw new LogicException('The type of this resource is read on its model.');
        }

        $kind = $this->resource->getAttribute('kind');

        return is_string($kind) ? $kind : throw new LogicException('Its model has no kind.');
    }
}
