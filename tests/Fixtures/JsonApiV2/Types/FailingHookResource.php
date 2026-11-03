<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A JSON:API resource whose attributes hook throws on the probe: its definition carries a `hook-failure` diagnostic.
 */
final class FailingHookResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        throw new RuntimeException('broken');
    }
}
