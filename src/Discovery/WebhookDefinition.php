<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Discovery;

use Ark4ne\OpenApi\Attributes\Webhook;

/**
 * A webhook declared by `#[Webhook]` on a class listed in `documents.<name>.webhooks`, without route.
 *
 * Webhooks declared on a controller action are routes: they come as RouteDefinition and a pass moves them.
 */
final readonly class WebhookDefinition
{
    /**
     * @param class-string $class
     */
    public function __construct(
        public string $class,
        public Webhook $webhook,
    ) {}
}
