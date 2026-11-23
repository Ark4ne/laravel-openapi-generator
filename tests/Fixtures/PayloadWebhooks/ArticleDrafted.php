<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks;

use Ark4ne\OpenApi\Attributes\Exclude;
use Ark4ne\OpenApi\Attributes\Webhook;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Excluded: never in a document, found or listed.
 */
#[Webhook('article.drafted')]
#[Exclude]
final class ArticleDrafted extends JsonResource {}
