<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Declares one response of an action; repeat it for each status.
 *
 * For its status, it takes precedence over what the generator infers. The body is given by a resource class or by
 * a PHPDoc type (`array{id: int, name: string}`, `list<UserResource>`), never both.
 *
 * On a `#[Webhook]` class, it declares a response expected from the receiver of the webhook.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Response
{
    /**
     * @param int|'default' $status
     * @param string|null $description defaults to the HTTP status phrase
     * @param class-string|null $resource JsonResource, ResourceCollection or any class the extractors know
     * @param string|null $type PHPDoc type of the body
     * @param array<string, string> $headers header name → PHPDoc type of its value
     * @param class-string|null $paginator LengthAwarePaginator, Paginator or CursorPaginator around the resource
     */
    public function __construct(
        public int|string $status = 200,
        public ?string $description = null,
        public ?string $resource = null,
        public ?string $type = null,
        public ?string $mediaType = null,
        public array $headers = [],
        public ?string $paginator = null,
    ) {}
}
