<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Webhooks;

use Ark4ne\OpenApi\Attributes\Response;
use Ark4ne\OpenApi\Attributes\Webhook;

final class PaymentWebhooks
{
    /**
     * A payment failed.
     */
    #[Webhook]
    public function failed(OrderPayload $request): void {}

    #[Webhook('payment.refunded')]
    #[Response(200, type: 'array{received: bool}')]
    public function refunded(): void {}
}
