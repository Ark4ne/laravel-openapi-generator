<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Declares a webhook: a request the application sends to its consumers (`webhooks` of the document, 3.1+).
 *
 * - On a class listed in `documents.<name>.webhooks` (event, notification, job…): the webhook is described without
 *   any route. The payload is a resource class or a PHPDoc type; `#[Response]` on the class gives the responses
 *   expected from the receiver (200 by default).
 * - On a controller action: the operation is written under `webhooks.<name>` instead of `paths`. Its request
 *   (form request, rules, `#[RequestBody]`) is the payload, its responses are those expected from the receiver.
 *
 * A route that receives webhooks from a third party (Stripe, GitHub…) is a regular path: it needs no attribute.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class Webhook
{
    /**
     * @param string $name key of the webhook in the document (`order.created`)
     * @param string $method HTTP method of the request sent
     * @param class-string|null $resource resource describing the payload
     * @param string|null $type PHPDoc type describing the payload, exclusive with `resource`
     * @param array<string, string> $headers headers sent with the payload (signature…) → PHPDoc type of their value
     * @param list<string> $tags
     */
    public function __construct(
        public string $name,
        public string $method = 'POST',
        public ?string $summary = null,
        public ?string $description = null,
        public ?string $resource = null,
        public ?string $type = null,
        public ?string $mediaType = null,
        public array $headers = [],
        public array $tags = [],
        public ?string $operationId = null,
    ) {}
}
