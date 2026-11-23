<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks;

use Ark4ne\OpenApi\Attributes\Webhook;
use Ark4ne\OpenApi\Tests\Fixtures\Webhooks\OrderPayload;

/**
 * A controller of route webhooks: not a payload class. Its `article.published` is replaced by the payload class.
 */
#[Webhook('article')]
final class LegacyWebhooks
{
    /**
     * The route webhook replaced by ArticlePublished.
     */
    public function published(OrderPayload $payload): void {}
}
