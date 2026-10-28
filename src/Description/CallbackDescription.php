<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Description;

/**
 * One request of a callback: the request sent to the URL of a runtime expression with one HTTP method.
 *
 * Callbacks sharing a name are grouped when writing (`callbacks.<name>.<expression>.<method>`): a name may hold
 * several expressions, an expression several methods.
 *
 * A callback that reuses a webhook carries its name only: a pass fills the operation once every route, webhooks
 * included, has been extracted, so that the result never depends on the order of the routes.
 */
final readonly class CallbackDescription extends Description
{
    /**
     * @param string $expression runtime expression of the URL (`{$request.body#/callbackUrl}`)
     * @param OperationDescription|null $operation the request sent and the responses expected from the receiver;
     *        `null` until the referenced webhook is resolved
     * @param string|null $webhook name of the webhook whose request and responses are reused
     */
    protected function __construct(
        public string $name,
        public string $expression,
        public ?OperationDescription $operation = null,
        public ?string $webhook = null,
    ) {}

    public static function create(string $name, string $expression, OperationDescription $operation): self
    {
        return new self($name, $expression, $operation);
    }

    /**
     * A callback reusing a webhook, resolved by a pass.
     */
    public static function fromWebhook(string $name, string $expression, string $webhook): self
    {
        return new self($name, $expression, webhook: $webhook);
    }

    public function operation(?OperationDescription $operation): static
    {
        return $this->copy(operation: $operation);
    }
}
