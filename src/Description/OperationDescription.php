<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Description;

use Ark4ne\OpenApiWriter\Enums\HttpMethod;

/**
 * Everything the generator understood of one HTTP method of one route.
 */
final readonly class OperationDescription extends Description
{
    /**
     * @param HttpMethod|string $method a fixed method of a Path Item, or another method in upper case (`PURGE`,
     *        `additionalOperations` from 3.2)
     * @param string $path OpenAPI path (`/users/{user}`), or the webhook name for a webhook
     * @param string|null $route route name, or `Controller@method`, for the diagnostics
     * @param list<string> $middlewares resolved middlewares of the route
     * @param list<string> $tags
     * @param list<TagDescription> $declaredTags tags declared with their details (`#[Tag]`), gathered in the
     *        document by the TagPass
     * @param list<ResponseDescription> $responses
     * @param list<SecurityDescription>|null $security `null` inherits the document security
     * @param array{url: string, description?: string}|null $externalDocs
     * @param list<CallbackDescription> $callbacks
     * @param list<ServerDescription> $servers servers of the operation (routes with a domain)
     * @param array<string, mixed> $extensions `x-*` extensions
     * @param bool $excluded left out of the document by the generator (a webhook without name)
     */
    protected function __construct(
        public HttpMethod|string $method,
        public string $path,
        public RequestDescription $request,
        public ?string $route = null,
        public array $middlewares = [],
        public ?string $operationId = null,
        public ?string $summary = null,
        public ?string $description = null,
        public array $tags = [],
        public array $declaredTags = [],
        public bool $deprecated = false,
        public ?array $externalDocs = null,
        public array $responses = [],
        public ?array $security = null,
        public array $callbacks = [],
        public array $servers = [],
        public array $extensions = [],
        public ?string $webhook = null,
        public bool $excluded = false,
    ) {}

    public static function create(HttpMethod|string $method, string $path): self
    {
        return new self($method, $path, RequestDescription::create());
    }

    public function path(string $path): static
    {
        return $this->copy(path: $path);
    }

    public function route(?string $route): static
    {
        return $this->copy(route: $route);
    }

    public function middlewares(string ...$middlewares): static
    {
        return $this->copy(middlewares: array_values($middlewares));
    }

    public function addMiddlewares(string ...$middlewares): static
    {
        return $this->copy(middlewares: [...$this->middlewares, ...array_values($middlewares)]);
    }

    public function operationId(?string $operationId): static
    {
        return $this->copy(operationId: $operationId);
    }

    public function summary(?string $summary): static
    {
        return $this->copy(summary: $summary);
    }

    public function description(?string $description): static
    {
        return $this->copy(description: $description);
    }

    public function tags(string ...$tags): static
    {
        return $this->copy(tags: array_values($tags));
    }

    public function addTags(string ...$tags): static
    {
        return $this->copy(tags: [...$this->tags, ...array_values($tags)]);
    }

    public function declaredTags(TagDescription ...$tags): static
    {
        return $this->copy(declaredTags: array_values($tags));
    }

    public function addDeclaredTags(TagDescription ...$tags): static
    {
        return $this->copy(declaredTags: [...$this->declaredTags, ...array_values($tags)]);
    }

    public function deprecated(bool $deprecated = true): static
    {
        return $this->copy(deprecated: $deprecated);
    }

    /**
     * @param array{url: string, description?: string}|null $externalDocs
     */
    public function externalDocs(?array $externalDocs): static
    {
        return $this->copy(externalDocs: $externalDocs);
    }

    public function request(RequestDescription $request): static
    {
        return $this->copy(request: $request);
    }

    public function responses(ResponseDescription ...$responses): static
    {
        return $this->copy(responses: array_values($responses));
    }

    public function addResponses(ResponseDescription ...$responses): static
    {
        return $this->copy(responses: [...$this->responses, ...array_values($responses)]);
    }

    /**
     * Without argument, the operation requires no authentication (`security: []`); `null` inherits the document's.
     */
    public function security(SecurityDescription ...$security): static
    {
        return $this->copy(security: array_values($security));
    }

    public function addSecurity(SecurityDescription ...$security): static
    {
        return $this->copy(security: [...$this->security ?? [], ...array_values($security)]);
    }

    public function withoutSecurity(): static
    {
        return $this->copy(security: null);
    }

    public function callbacks(CallbackDescription ...$callbacks): static
    {
        return $this->copy(callbacks: array_values($callbacks));
    }

    public function addCallbacks(CallbackDescription ...$callbacks): static
    {
        return $this->copy(callbacks: [...$this->callbacks, ...array_values($callbacks)]);
    }

    public function servers(ServerDescription ...$servers): static
    {
        return $this->copy(servers: array_values($servers));
    }

    public function addServers(ServerDescription ...$servers): static
    {
        return $this->copy(servers: [...$this->servers, ...array_values($servers)]);
    }

    /**
     * Writes the operation under `webhooks.<name>` instead of `paths` (3.1+): a request the application sends.
     * Set by the operation extractor that reads `#[Webhook]`; the generator then files the operation in
     * `DocumentDescription::$webhooks`, so that the passes tied to routes (middlewares) never see it.
     */
    public function webhook(?string $name): static
    {
        return $this->copy(webhook: $name);
    }

    /**
     * Leaves the operation out of the document: set by an extractor that finds the route cannot be written.
     */
    public function exclude(bool $excluded = true): static
    {
        return $this->copy(excluded: $excluded);
    }

    /**
     * @param string $name with or without the `x-` prefix
     */
    public function x(string $name, mixed $value): static
    {
        return $this->copy(extensions: [...$this->extensions, $name => $value]);
    }
}
