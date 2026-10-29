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
     * @param array<string, array<string, string>> $encodings media type (`multipart/form-data`) → top-level property →
     *        content type of its part (`image/png, image/jpeg`)
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

    /**
     * Removes a media type with its encodings.
     */
    public function withoutContent(string $mediaType): static
    {
        return $this->copy(
            contents: array_diff_key($this->contents, [$mediaType => true]),
            encodings: array_diff_key($this->encodings, [$mediaType => true]),
        );
    }

    /**
     * @param array<string, array<string, string>> $encodings replaces every encoding of every media type
     */
    public function encodings(array $encodings): static
    {
        return $this->copy(encodings: $encodings);
    }

    /**
     * Content type of one part of a multipart body, from the `mimes`, `mimetypes` and `File::types()` rules.
     *
     * @param string $property top-level property of the media type's schema: a rule on `photos.*` gives `photos`
     */
    public function encoding(string $mediaType, string $property, string $contentType): static
    {
        return $this->copy(encodings: [
            ...$this->encodings,
            $mediaType => [...$this->encodings[$mediaType] ?? [], $property => $contentType],
        ]);
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
