<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks;

use Ark4ne\OpenApi\Attributes\Header;
use Ark4ne\OpenApi\Attributes\Webhook;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An article was published.
 *
 * Sent once, when the article goes live.
 *
 * @mixin Article
 */
#[Webhook('article.published')]
#[Header('X-Signature', required: true, description: 'HMAC of the body')]
#[Header('X-Attempt', type: 'positive-int', example: 1)]
final class ArticlePublished extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->whenLoaded('author'),
        ];
    }
}
