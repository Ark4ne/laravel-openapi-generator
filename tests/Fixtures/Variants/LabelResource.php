<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Variants;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class LabelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['label' => 'A label'];
    }
}
