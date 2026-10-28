<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Description;

use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * One response of an operation, or a reference to a shared response of the document.
 */
final readonly class ResponseDescription extends Description
{
    /**
     * @param int|'default' $status
     * @param list<HeaderDescription> $headers
     * @param array<string, Schema> $contents media type → schema
     * @param array<string, Schema> $itemContents sequential media type (`text/event-stream`) → schema of one item
     * @param string|null $reference name of a shared response of the document
     */
    protected function __construct(
        public int|string $status,
        public ?string $description = null,
        public array $headers = [],
        public array $contents = [],
        public array $itemContents = [],
        public ResponseOrigin $origin = ResponseOrigin::Inferred,
        public ?string $reference = null,
    ) {}

    /**
     * @param int|'default' $status
     */
    public static function create(int|string $status): self
    {
        return new self($status);
    }

    public static function ok(): self
    {
        return new self(200);
    }

    /**
     * A response written as a `$ref` to `components.responses.<name>`.
     *
     * @param int|'default' $status
     */
    public static function reference(int|string $status, string $name): self
    {
        return new self($status, reference: $name);
    }

    /**
     * @param int|'default' $status
     */
    public function status(int|string $status): static
    {
        return $this->copy(status: $status);
    }

    public function description(?string $description): static
    {
        return $this->copy(description: $description);
    }

    public function headers(HeaderDescription ...$headers): static
    {
        return $this->copy(headers: array_values($headers));
    }

    public function addHeaders(HeaderDescription ...$headers): static
    {
        return $this->copy(headers: [...$this->headers, ...array_values($headers)]);
    }

    /**
     * @param array<string, Schema> $contents replaces every media type
     */
    public function contents(array $contents): static
    {
        return $this->copy(contents: $contents);
    }

    public function content(string $mediaType, Schema $schema): static
    {
        return $this->copy(contents: [...$this->contents, $mediaType => $schema]);
    }

    public function withoutContent(string $mediaType): static
    {
        return $this->copy(contents: array_diff_key($this->contents, [$mediaType => true]));
    }

    /**
     * @param array<string, Schema> $contents replaces every sequential media type
     */
    public function itemContents(array $contents): static
    {
        return $this->copy(itemContents: $contents);
    }

    /**
     * Sets the schema of one item of a sequential media type: `itemSchema` in 3.2.
     */
    public function itemContent(string $mediaType, Schema $schema): static
    {
        return $this->copy(itemContents: [...$this->itemContents, $mediaType => $schema]);
    }

    public function origin(ResponseOrigin $origin): static
    {
        return $this->copy(origin: $origin);
    }
}
