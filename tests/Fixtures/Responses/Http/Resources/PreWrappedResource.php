<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Its data already holds `data`: Laravel does not wrap it again.
 */
final class PreWrappedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['data' => ['id' => 1], 'count' => 1];
    }
}
