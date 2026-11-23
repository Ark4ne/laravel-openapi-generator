<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks;

use Ark4ne\OpenApi\Attributes\Operation;
use Ark4ne\OpenApi\Attributes\Response;
use Ark4ne\OpenApi\Attributes\Webhook;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Archived, read from the PHPDoc.
 *
 * @mixin Article
 *
 * @deprecated
 */
#[Webhook('article.archived', method: 'PUT')]
#[Operation(summary: 'An article was archived', operationId: 'articleArchived', tags: ['Articles'])]
#[Response(204, description: 'Received')]
#[Response(410, description: 'The subscription is cancelled')]
final class ArticleArchived extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }
}
