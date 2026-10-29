<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Declares one response of an action; repeat it for each status. On an exception class, it describes the response
 * Laravel renders for the exception.
 *
 * For its status, it takes precedence over what the generator infers; a declared success (2xx) also leaves out the
 * successes inferred for other statuses (a `JsonResponse` built with 201 is inferred as a 200). The body is given by a resource class or by
 * a PHPDoc type (`array{id: int, name: string}`, `list<UserResource>`), never both.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Response
{
    /**
     * @param int|'default' $status
     * @param string|null $description defaults to the HTTP status phrase
     * @param string|null $summary short summary, written from 3.2 only
     * @param class-string|null $resource JsonResource, ResourceCollection or any class the extractors know
     * @param string|null $type PHPDoc type of the body
     * @param array<string, string> $headers header name → PHPDoc type of its value
     * @param class-string|null $paginator LengthAwarePaginator, Paginator or CursorPaginator around the resource
     * @param string|null $mediaType `application/json` by default; `text/event-stream`: the type is one event
     * @param mixed $example example of the body
     */
    public function __construct(
        public int|string $status = 200,
        public ?string $description = null,
        public ?string $resource = null,
        public ?string $type = null,
        public ?string $mediaType = null,
        public array $headers = [],
        public ?string $paginator = null,
        public ?string $summary = null,
        public mixed $example = null,
    ) {}
}
