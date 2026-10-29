<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources;

use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;

/**
 * Fails once its relations are loaded.
 *
 * @mixin Article
 *
 * @property-read Article $resource
 */
final class ArticleWithBrokenAuthorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->resource->relationLoaded('author')) {
            throw new RuntimeException('broken author');
        }

        return ['id' => $this->resource->getKey(), 'author' => null];
    }
}
