<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Webhooks;

use Ark4ne\OpenApi\Attributes\Header;
use Ark4ne\OpenApi\Attributes\Operation;
use Ark4ne\OpenApi\Attributes\Webhook;

/**
 * The webhooks sent about orders: `order.<action>`.
 */
#[Webhook('order')]
final class OrderWebhooks
{
    #[Operation(summary: 'An order was created', operationId: 'orderCreated', tags: ['Webhooks'])]
    #[Header('X-Signature', type: 'string', required: true)]
    public function created(OrderPayload $request): void {}

    #[Webhook('cancelled')]
    public function cancel(OrderPayload $request): void {}
}
