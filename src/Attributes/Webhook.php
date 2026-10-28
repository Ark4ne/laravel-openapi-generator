<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Marks an action, or every action of a controller, as a webhook: a request the application sends to its
 * consumers. The operation is written under `webhooks.<name>` (3.1+) instead of `paths`.
 *
 * Everything else is read as for any operation: the request (form request, rules, `#[RequestBody]`, `#[Header]`)
 * describes the payload sent, the responses describe what the receiver must answer, `#[Operation]` gives the
 * summary, the tags and the operationId.
 *
 * A route that receives webhooks from a third party (Stripe, GitHub…) is a regular path: it needs no attribute.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class Webhook
{
    /**
     * @param string|null $name key of the webhook in the document (`order.created`); the route name by default
     */
    public function __construct(
        public ?string $name = null,
    ) {}
}
