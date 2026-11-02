<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Illuminate\Http\Request;

/**
 * A JSON:API resource still declaring the 1.x `toMeta()`: the package cannot compile its definition.
 */
final class RenamedHookResource extends JsonApiResource
{
    /**
     * @return array<string, string>
     */
    protected function toMeta(Request $request): array
    {
        return ['version' => '1.0'];
    }
}
