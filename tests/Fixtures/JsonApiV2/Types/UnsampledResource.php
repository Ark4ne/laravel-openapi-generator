<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * A JSON:API resource without model to sample, and without relationship: its raw value cannot be typed.
 */
final class UnsampledResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        return [
            'label' => $this->string(),
            'raw' => fn(): mixed => $this->resource->raw,
        ];
    }
}
