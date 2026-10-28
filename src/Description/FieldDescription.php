<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Description;

use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * One validated field (`email`, `tags.*`, `address.city`), as the rule mappers build it.
 *
 * The request extractors then place the fields: in the body, in the query, or as files of a multipart body.
 */
final readonly class FieldDescription extends Description
{
    /**
     * @param list<string> $conditions human-readable conditions (`required if type is "company"`)
     */
    protected function __construct(
        public string $name,
        public Schema $schema,
        public bool $required = false,
        public array $conditions = [],
        public bool $file = false,
    ) {}

    public static function create(string $name): self
    {
        return new self($name, Schema::create());
    }

    public function schema(Schema $schema): static
    {
        return $this->copy(schema: $schema);
    }

    public function required(bool $required = true): static
    {
        return $this->copy(required: $required);
    }

    public function conditions(string ...$conditions): static
    {
        return $this->copy(conditions: array_values($conditions));
    }

    public function addConditions(string ...$conditions): static
    {
        return $this->copy(conditions: [...$this->conditions, ...array_values($conditions)]);
    }

    /**
     * Marks the field as an uploaded file: the body becomes `multipart/form-data`.
     */
    public function file(bool $file = true): static
    {
        return $this->copy(file: $file);
    }
}
