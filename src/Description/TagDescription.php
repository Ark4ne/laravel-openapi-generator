<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Description;

final readonly class TagDescription extends Description
{
    protected function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $parent = null,
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
     * Groups the tag under another one: `Tag.parent` in 3.2, `x-tagGroups` before.
     */
    public function parent(?string $parent): static
    {
        return $this->copy(parent: $parent);
    }
}
