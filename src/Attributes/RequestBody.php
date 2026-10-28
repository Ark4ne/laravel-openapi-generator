<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Attributes;

use Attribute;

/**
 * Describes the request body of an action when it does not come from a form request.
 *
 * The body is a PHPDoc type (`array{name: string, tags?: list<string>}`) or a resource class. Without either, the
 * attribute only gives the description of the body the rules describe, and whether it is required.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class RequestBody
{
    /**
     * @param class-string|null $resource
     * @param bool|null $required `null`: required when the attribute gives the body (`type`), unchanged otherwise
     */
    public function __construct(
        public ?string $type = null,
        public ?string $resource = null,
        public ?string $mediaType = null,
        public ?string $description = null,
        public ?bool $required = null,
    ) {}
}
