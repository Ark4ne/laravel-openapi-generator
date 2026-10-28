<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Description;

use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * A request body: one schema per media type.
 */
final readonly class BodyDescription extends Description
{
    /**
     * @param array<string, Schema> $contents media type → schema
     * @param array<string, string> $encodings multipart field → content type of its part (`image/png, image/jpeg`)
     */
    protected function __construct(
        public array $contents = [],
        public array $encodings = [],
        public bool $required = false,
        public ?string $description = null,
    ) {}

    public static function create(): self
    {
        return new self();
    }

    /**
     * @param array<string, Schema> $contents replaces every media type
     */
    public function contents(array $contents): static
    {
        return $this->copy(contents: $contents);
    }

    /**
     * Sets the schema of one media type, replacing the previous one.
     */
    public function content(string $mediaType, Schema $schema): static
    {
        return $this->copy(contents: [...$this->contents, $mediaType => $schema]);
    }

    public function withoutContent(string $mediaType): static
    {
        return $this->copy(contents: array_diff_key($this->contents, [$mediaType => true]));
    }

    /**
     * @param array<string, string> $encodings replaces every field encoding
     */
    public function encodings(array $encodings): static
    {
        return $this->copy(encodings: $encodings);
    }

    /**
     * Content type of one part of a multipart body, from the `mimes`, `mimetypes` and `File::types()` rules.
     */
    public function encoding(string $field, string $contentType): static
    {
        return $this->copy(encodings: [...$this->encodings, $field => $contentType]);
    }

    public function required(bool $required = true): static
    {
        return $this->copy(required: $required);
    }

    public function description(?string $description): static
    {
        return $this->copy(description: $description);
    }
}
