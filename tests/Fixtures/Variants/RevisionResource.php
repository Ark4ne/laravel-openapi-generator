<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Variants;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource that renders what its request carries: the variant header and what the integrations prepared.
 */
final class RevisionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $prepared = $request->attributes->get('prepared');

        return [
            'revision' => $request->header('Api-Version') ?? 'none',
            'prepared' => is_string($prepared) ? $prepared : 'none',
        ];
    }
}
