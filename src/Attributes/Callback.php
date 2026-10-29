<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Ark4ne\OpenApi\Exceptions\InvalidAttributeException;
use Attribute;

/**
 * Declares a callback of an operation: a request the application sends later, in reaction to this call, to a URL
 * given by the call (`Operation.callbacks`, every version).
 *
 * The URL is a runtime expression: `{$request.body#/callbackUrl}`, `{$request.query.notify}`. The request sent is
 * described by exactly one of: the webhook it shares its payload and responses with, a resource, a PHPDoc type.
 * Giving several of them, or a `status` together with `webhook` (the webhook's responses apply), throws an
 * InvalidAttributeException.
 *
 * Repeat the attribute with the same name for several methods or URLs of one callback.
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
     * @param string $method HTTP method of the request; with `webhook`, those of the webhook apply
     * @param int|null $status response expected from the receiver, 200 by default; not with `webhook`
     *
     * @throws InvalidAttributeException
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
        public ?int $status = null,
    ) {
        if (count(array_filter([$webhook, $resource, $type], static fn(?string $source): bool => $source !== null)) > 1) {
            throw InvalidAttributeException::because('webhook, resource and type are mutually exclusive');
        }

        if ($webhook !== null && $status !== null) {
            throw InvalidAttributeException::because('the responses of the webhook apply, status cannot be given with webhook');
        }
    }
}
