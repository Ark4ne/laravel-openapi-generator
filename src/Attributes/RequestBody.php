<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Describes the request body of an action when it does not come from a form request.
 *
 * The body is a PHPDoc type (`array{name: string, tags?: list<string>}`) or a resource class.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class RequestBody
{
    /**
     * @param class-string|null $resource
     */
    public function __construct(
        public ?string $type = null,
        public ?string $resource = null,
        public ?string $mediaType = null,
        public ?string $description = null,
        public bool $required = true,
    ) {}
}
