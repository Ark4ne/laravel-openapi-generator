<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources;

use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Nests ArticleResource, whose relations must be described as at the top level.
 *
 * @mixin Article
 *
 * @property-read Article $resource
 */
final class WrapperResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['article' => ArticleResource::make($this->resource)];
    }
}
