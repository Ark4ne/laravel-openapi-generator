<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Without wrapper, with additional information: Laravel puts the data under `data`.
 */
final class UnwrappedResource extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => 1];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return ['meta' => ['version' => '1']];
    }
}
