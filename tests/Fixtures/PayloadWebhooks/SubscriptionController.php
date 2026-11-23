<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks;

use Ark4ne\OpenApi\Attributes\Callback;

/**
 * A callback reusing a payload webhook.
 */
final class SubscriptionController
{
    #[Callback('published', url: '{$request.body#/callbackUrl}', webhook: 'article.published')]
    public function store(): void {}
}
