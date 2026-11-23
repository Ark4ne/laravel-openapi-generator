<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks;

use Ark4ne\OpenApi\Attributes\Tag;
use Ark4ne\OpenApi\Attributes\Webhook;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An article was reviewed.
 *
 * @mixin Article
 */
#[Webhook('article.reviewed')]
#[Tag('Reviews', description: 'The editorial reviews')]
final class ArticleReviewed extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'title' => $this->title];
    }
}
