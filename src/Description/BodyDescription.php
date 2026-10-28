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
     */
    protected function __construct(
        public array $contents = [],
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

    public function required(bool $required = true): static
    {
        return $this->copy(required: $required);
    }

    public function description(?string $description): static
    {
        return $this->copy(description: $description);
    }
}
