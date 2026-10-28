<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Types;

use Ark4ne\OpenApi\Attributes\Callback;
use Ark4ne\OpenApi\Attributes\Operation;
use Ark4ne\OpenApi\Attributes\Webhook;
use Ark4ne\OpenApi\Tests\Fixtures\Webhooks\OrderPayload;

/**
 * Webhooks with attributes that cannot be built (unknown argument), and a payload that is not a resource.
 */
#[Webhook(nmae: 'typo')]
final class BrokenWebhooks
{
    #[Webhook('broken.sent')]
    #[Operation(sumary: 'Typo')]
    public function sent(): void {}

    #[Callback('dto', url: '{$request.body#/url}', resource: OrderPayload::class)]
    public function dto(): void {}
}
