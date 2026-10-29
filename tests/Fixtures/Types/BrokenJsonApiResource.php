<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A JSON:API resource whose attributes cannot be read.
 */
final class BrokenJsonApiResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        throw new RuntimeException('broken');
    }
}
