<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Webhooks\Payloads;

use Ark4ne\OpenApi\Attributes\Header;
use Ark4ne\OpenApi\Attributes\Operation;
use Ark4ne\OpenApi\Attributes\Tag;
use Ark4ne\OpenApi\Attributes\Webhook;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Author;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * docs.webhooks.author
 *
 * @mixin Author
 */
#[Webhook('author.updated', method: 'patch')]
#[Operation(sumary: 'Typo')]
#[Tag('Audit')]
#[Header('X-Request-Id', type: 'non-empty-string', description: 'Correlation id')]
#[Header('x-request-id', required: true, example: 'f3a1')]
#[Header('X-Trace', example: 'abc')]
final class TranslatedPayload extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }
}
