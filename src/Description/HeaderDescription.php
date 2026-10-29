<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Description;

use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * A response header.
 */
final readonly class HeaderDescription extends Description
{
    protected function __construct(
        public string $name,
        public Schema $schema,
        public ?string $description = null,
        public bool $required = false,
    ) {}

    public static function create(string $name, ?Schema $schema = null): self
    {
        return new self($name, $schema ?? Schema::string());
    }

    public function schema(Schema $schema): static
    {
        return $this->copy(schema: $schema);
    }

    public function description(?string $description): static
    {
        return $this->copy(description: $description);
    }

    public function required(bool $required = true): static
    {
        return $this->copy(required: $required);
    }
}
