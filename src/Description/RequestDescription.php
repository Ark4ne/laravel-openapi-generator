<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Description;

/**
 * What an operation receives.
 */
final readonly class RequestDescription extends Description
{
    /**
     * @param list<ParameterDescription> $parameters
     * @param list<FieldDescription> $fields validated fields, not yet placed in the body or the query
     */
    protected function __construct(
        public array $parameters = [],
        public array $fields = [],
        public ?BodyDescription $body = null,
        public bool $validated = false,
        public bool $authorized = false,
    ) {}

    public static function create(): self
    {
        return new self();
    }

    public function parameters(ParameterDescription ...$parameters): static
    {
        return $this->copy(parameters: array_values($parameters));
    }

    public function addParameters(ParameterDescription ...$parameters): static
    {
        return $this->copy(parameters: [...$this->parameters, ...array_values($parameters)]);
    }

    public function fields(FieldDescription ...$fields): static
    {
        return $this->copy(fields: array_values($fields));
    }

    public function addFields(FieldDescription ...$fields): static
    {
        return $this->copy(fields: [...$this->fields, ...array_values($fields)]);
    }

    public function body(?BodyDescription $body): static
    {
        return $this->copy(body: $body);
    }

    /**
     * The request runs validation rules: the operation can answer 422.
     */
    public function validated(bool $validated = true): static
    {
        return $this->copy(validated: $validated);
    }

    /**
     * The request runs an authorization check: the operation can answer 403.
     */
    public function authorized(bool $authorized = true): static
    {
        return $this->copy(authorized: $authorized);
    }
}
