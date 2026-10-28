<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Declares a callback of an operation: a request the application sends later, in reaction to this call, to a URL
 * given by the call (`Operation.callbacks`, every version).
 *
 * The URL is a runtime expression: `{$request.body#/callbackUrl}`, `{$request.query.notify}`. The request sent is
 * described either by the webhook it shares its payload with, or by a resource or a PHPDoc type.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Callback
{
    /**
     * @param string $name key of the callback in the operation (`orderShipped`)
     * @param string $url runtime expression of the URL called
     * @param string|null $webhook name of a webhook whose request and responses the callback reuses
     * @param class-string|null $resource resource describing the payload
     * @param string|null $type PHPDoc type describing the payload
     * @param int $status response expected from the receiver
     */
    public function __construct(
        public string $name,
        public string $url,
        public string $method = 'POST',
        public ?string $webhook = null,
        public ?string $resource = null,
        public ?string $type = null,
        public ?string $mediaType = null,
        public ?string $description = null,
        public int $status = 200,
    ) {}
}
