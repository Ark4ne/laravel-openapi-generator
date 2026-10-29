<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Requests;

use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApiWriter\Enums\ParameterLocation;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * The manual description of a request (DescribesRequest): immutable, each method returns a copy.
 *
 * Rules are validation rules, as in `rules()`; a Schema of the writer is used as it is.
 */
final readonly class RequestDescriber
{
    /**
     * @param array<string, mixed> $query field → rules
     * @param array<string, array<string, mixed>> $bodies media type → field → rules
     * @param array<string, Schema> $schemas media type → schema
     * @param list<array{in: ParameterLocation, name: string, rules: Schema|string|array<array-key, mixed>, description: string|null, required: bool}> $headers
     *        headers and cookies
     * @param list<ParameterDescription> $parameters
     */
    private function __construct(
        public array $query = [],
        public array $bodies = [],
        public array $schemas = [],
        public array $headers = [],
        public array $parameters = [],
        public ?string $bodyDescription = null,
    ) {}

    public static function create(): self
    {
        return new self();
    }

    /**
     * Query fields, added to those of the rules.
     *
     * @param array<string, mixed> $rules
     */
    public function query(array $rules): self
    {
        return new self([...$this->query, ...$rules], $this->bodies, $this->schemas, $this->headers, $this->parameters, $this->bodyDescription);
    }

    /**
     * Body fields of a media type (`application/json` by default, `multipart/form-data` with a file).
     *
     * @param array<string, mixed> $rules
     */
    public function body(array $rules, ?string $mediaType = null): self
    {
        $key = $mediaType ?? '';

        return new self($this->query, [...$this->bodies, $key => [...$this->bodies[$key] ?? [], ...$rules]], $this->schemas, $this->headers, $this->parameters, $this->bodyDescription);
    }

    /**
     * @param array<string, mixed> $rules
     */
    public function json(array $rules): self
    {
        return $this->body($rules, 'application/json');
    }

    /**
     * @param array<string, mixed> $rules
     */
    public function form(array $rules): self
    {
        return $this->body($rules, 'application/x-www-form-urlencoded');
    }

    /**
     * @param array<string, mixed> $rules
     */
    public function multipart(array $rules): self
    {
        return $this->body($rules, 'multipart/form-data');
    }

    /**
     * A body described by a schema of the writer, replacing that media type.
     */
    public function bodySchema(Schema $schema, string $mediaType = 'application/json'): self
    {
        return new self($this->query, $this->bodies, [...$this->schemas, $mediaType => $schema], $this->headers, $this->parameters, $this->bodyDescription);
    }

    public function bodyDescription(?string $description): self
    {
        return new self($this->query, $this->bodies, $this->schemas, $this->headers, $this->parameters, $description);
    }

    /**
     * @param Schema|string|array<array-key, mixed> $rules validation rules, or a schema
     */
    public function header(string $name, Schema|string|array $rules = 'string', ?string $description = null, bool $required = false): self
    {
        return $this->entry(ParameterLocation::Header, $name, $rules, $description, $required);
    }

    /**
     * @param Schema|string|array<array-key, mixed> $rules validation rules, or a schema
     */
    public function cookie(string $name, Schema|string|array $rules = 'string', ?string $description = null, bool $required = false): self
    {
        return $this->entry(ParameterLocation::Cookie, $name, $rules, $description, $required);
    }

    /**
     * Any parameter, as it is; it replaces the parameter of the same name and location.
     */
    public function parameter(ParameterDescription $parameter): self
    {
        return new self($this->query, $this->bodies, $this->schemas, $this->headers, [...$this->parameters, $parameter], $this->bodyDescription);
    }

    /**
     * @param Schema|string|array<array-key, mixed> $rules
     */
    private function entry(ParameterLocation $in, string $name, Schema|string|array $rules, ?string $description, bool $required): self
    {
        $entry = ['in' => $in, 'name' => $name, 'rules' => $rules, 'description' => $description, 'required' => $required];

        return new self($this->query, $this->bodies, $this->schemas, [...$this->headers, $entry], $this->parameters, $this->bodyDescription);
    }
}
