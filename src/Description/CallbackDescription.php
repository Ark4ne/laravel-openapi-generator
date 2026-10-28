<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Description;

/**
 * A callback of an operation: the request sent to the URL of a runtime expression.
 */
final readonly class CallbackDescription extends Description
{
    /**
     * @param string $expression runtime expression of the URL (`{$request.body#/callbackUrl}`)
     * @param OperationDescription $operation the request sent and the responses expected from the receiver
     */
    protected function __construct(
        public string $name,
        public string $expression,
        public OperationDescription $operation,
    ) {}

    public static function create(string $name, string $expression, OperationDescription $operation): self
    {
        return new self($name, $expression, $operation);
    }

    public function operation(OperationDescription $operation): static
    {
        return $this->copy(operation: $operation);
    }
}
