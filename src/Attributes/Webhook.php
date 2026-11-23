<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Marks a webhook: a request the application sends to its consumers, written under `webhooks.<name>` (3.1+) instead
 * of `paths`.
 *
 * On a `JsonResource` found by the discovery (`webhooks.paths`, `webhooks.classes`), it declares a **payload
 * webhook**: the resource is the body sent, no route is needed. The name is required; `#[Operation]`, `#[Tag]`,
 * `#[Header]` and `#[Response]` on the class complete it, its PHPDoc gives the summary and the description.
 *
 * On an action, or on every action of a controller, it marks a **route webhook** (deprecated: declare the payload
 * class instead).
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
     * @param string|null $name on an action: key of the webhook in the document (`order.created`), the route name
     *        by default. On a controller: prefix of the keys of its actions (`order` gives `order.created`,
     *        `order.updated`, the suffix being the action's own name, else the method name), so that two actions
     *        never share a key. On a payload class: the key of the webhook, required.
     * @param string|null $method on a payload class: the HTTP method of the request sent, `POST` by default. Ignored
     *        on a route webhook, whose method is the route's.
     */
    public function __construct(
        public ?string $name = null,
        public ?string $method = null,
    ) {}
}
