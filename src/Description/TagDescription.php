<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Description;

final readonly class TagDescription extends Description
{
    /**
     * @param array{url: string, description?: string}|null $externalDocs
     */
    protected function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $parent = null,
        public ?string $summary = null,
        public ?string $kind = null,
        public ?array $externalDocs = null,
    ) {}

    public static function create(string $name): self
    {
        return new self($name);
    }

    public function description(?string $description): static
    {
        return $this->copy(description: $description);
    }

    /**
     * Written from 3.2 only.
     */
    public function summary(?string $summary): static
    {
        return $this->copy(summary: $summary);
    }

    /**
     * `nav`, `badge`, `audience`… Written from 3.2 only.
     */
    public function kind(?string $kind): static
    {
        return $this->copy(kind: $kind);
    }

    /**
     * @param array{url: string, description?: string}|null $externalDocs
     */
    public function externalDocs(?array $externalDocs): static
    {
        return $this->copy(externalDocs: $externalDocs);
    }

    /**
     * Groups the tag under another one: `Tag.parent` in 3.2, `x-tagGroups` before.
     */
    public function parent(?string $parent): static
    {
        return $this->copy(parent: $parent);
    }
}
