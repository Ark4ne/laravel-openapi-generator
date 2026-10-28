<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Description;

use Ark4ne\OpenApiWriter\Objects\Schema;
use Ark4ne\OpenApiWriter\Objects\SecurityScheme;

/**
 * Every operation of a document and what they share: input and output of the passes.
 */
final readonly class DocumentDescription extends Description
{
    /**
     * @param list<OperationDescription> $operations
     * @param list<OperationDescription> $webhooks requests the application sends, keyed by their `webhook` name
     * @param list<TagDescription> $tags
     * @param array<string, Schema> $schemas component name → schema
     * @param array<string, ResponseDescription> $responses component name → shared response
     * @param array<string, SecurityScheme> $securitySchemes component name → scheme
     * @param list<SecurityDescription> $security default security of the operations
     */
    protected function __construct(
        public array $operations = [],
        public array $webhooks = [],
        public array $tags = [],
        public array $schemas = [],
        public array $responses = [],
        public array $securitySchemes = [],
        public array $security = [],
    ) {}

    public static function create(): self
    {
        return new self();
    }

    public function operations(OperationDescription ...$operations): static
    {
        return $this->copy(operations: array_values($operations));
    }

    public function addOperations(OperationDescription ...$operations): static
    {
        return $this->copy(operations: [...$this->operations, ...array_values($operations)]);
    }

    public function webhooks(OperationDescription ...$webhooks): static
    {
        return $this->copy(webhooks: array_values($webhooks));
    }

    public function addWebhooks(OperationDescription ...$webhooks): static
    {
        return $this->copy(webhooks: [...$this->webhooks, ...array_values($webhooks)]);
    }

    public function tags(TagDescription ...$tags): static
    {
        return $this->copy(tags: array_values($tags));
    }

    public function addTags(TagDescription ...$tags): static
    {
        return $this->copy(tags: [...$this->tags, ...array_values($tags)]);
    }

    /**
     * @param array<string, Schema> $schemas
     */
    public function schemas(array $schemas): static
    {
        return $this->copy(schemas: $schemas);
    }

    public function schema(string $name, Schema $schema): static
    {
        return $this->copy(schemas: [...$this->schemas, $name => $schema]);
    }

    /**
     * @param array<string, ResponseDescription> $responses
     */
    public function responses(array $responses): static
    {
        return $this->copy(responses: $responses);
    }

    public function response(string $name, ResponseDescription $response): static
    {
        return $this->copy(responses: [...$this->responses, $name => $response]);
    }

    /**
     * @param array<string, SecurityScheme> $schemes
     */
    public function securitySchemes(array $schemes): static
    {
        return $this->copy(securitySchemes: $schemes);
    }

    public function securityScheme(string $name, SecurityScheme $scheme): static
    {
        return $this->copy(securitySchemes: [...$this->securitySchemes, $name => $scheme]);
    }

    public function security(SecurityDescription ...$security): static
    {
        return $this->copy(security: array_values($security));
    }

    public function addSecurity(SecurityDescription ...$security): static
    {
        return $this->copy(security: [...$this->security, ...array_values($security)]);
    }
}
