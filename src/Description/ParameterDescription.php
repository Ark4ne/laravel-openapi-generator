<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Description;

use Ark4ne\OpenApiWriter\Enums\ParameterLocation;
use Ark4ne\OpenApiWriter\Enums\ParameterStyle;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * A path, query, header or cookie parameter.
 */
final readonly class ParameterDescription extends Description
{
    /**
     * @param class-string|null $boundTo model or enum bound to a path parameter (404 response, key type)
     */
    protected function __construct(
        public string $name,
        public ParameterLocation $in,
        public Schema $schema,
        public bool $required = false,
        public ?string $description = null,
        public bool $deprecated = false,
        public ?ParameterStyle $style = null,
        public ?bool $explode = null,
        public ?string $boundTo = null,
    ) {}

    public static function path(string $name, ?Schema $schema = null): self
    {
        return new self($name, ParameterLocation::Path, $schema ?? Schema::string(), required: true);
    }

    public static function query(string $name, ?Schema $schema = null): self
    {
        return new self($name, ParameterLocation::Query, $schema ?? Schema::string());
    }

    /**
     * The whole query string as one parameter (3.2): its schema describes every query field.
     */
    public static function querystring(string $name, Schema $schema): self
    {
        return new self($name, ParameterLocation::Querystring, $schema);
    }

    public static function header(string $name, ?Schema $schema = null): self
    {
        return new self($name, ParameterLocation::Header, $schema ?? Schema::string());
    }

    public static function cookie(string $name, ?Schema $schema = null): self
    {
        return new self($name, ParameterLocation::Cookie, $schema ?? Schema::string());
    }

    public function schema(Schema $schema): static
    {
        return $this->copy(schema: $schema);
    }

    public function required(bool $required = true): static
    {
        return $this->copy(required: $required);
    }

    public function description(?string $description): static
    {
        return $this->copy(description: $description);
    }

    public function deprecated(bool $deprecated = true): static
    {
        return $this->copy(deprecated: $deprecated);
    }

    public function style(?ParameterStyle $style): static
    {
        return $this->copy(style: $style);
    }

    public function explode(?bool $explode = true): static
    {
        return $this->copy(explode: $explode);
    }

    /**
     * @param class-string|null $class
     */
    public function boundTo(?string $class): static
    {
        return $this->copy(boundTo: $class);
    }
}
