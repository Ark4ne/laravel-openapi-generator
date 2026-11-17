<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A declaration the package cannot compile.
 *
 * @extends JsonApiResource<\Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Models\Post>
 */
final class FailingHookResource extends JsonApiResource
{
    public function toAttributes(Request $request): iterable
    {
        throw new RuntimeException('broken');
    }
}
