<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Preparation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource that renders what the integrations prepared on its request.
 */
final class PreparedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $prepared = $request->attributes->get('prepared');

        return ['prepared' => is_string($prepared) ? $prepared : 'none'];
    }
}
