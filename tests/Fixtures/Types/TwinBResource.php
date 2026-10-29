<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * One of two JSON:API resources of the same type.
 */
final class TwinBResource extends JsonApiResource
{
    protected function toType(Request $request): string
    {
        return 'twin';
    }
}
