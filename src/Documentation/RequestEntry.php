<?php

namespace Ark4ne\OpenApi\Documentation;

class RequestEntry
{
    /**
     * @param array<string, Request\Security> $securities
     * @param array<string, Request\Parameter> $parameters
     * @param array<string, Request\Parameter> $headers
     * @param array<string, Request\Parameter> $body
     * @param array<string, Request\Parameter> $queries
     */
    public function __construct(
        protected array $securities = [],
        protected array $parameters = [],
        protected array $headers = [],
        protected array $body = [],
        protected array $queries = [],
    )
    {
    }

    /**
     * @return array<string, Request\Security>
     */
    public function securities(): array
    {
        return $this->securities;
    }

    /**
     * @return array<string, Request\Parameter>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * @return array<string, Request\Parameter>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * @return array<string, Request\Parameter>
     */
    public function body(): array
    {
        return $this->body;
    }

    /**
     * @return array<string, Request\Parameter>
     */
    public function queries(): array
    {
        return $this->queries;
    }

    public function hasRules(): bool
    {
        return !empty($this->queries) || !empty($this->body);
    }

    public function addSecurity(Request\Security $security): self
    {
        $this->securities[] = $security;

        return $this;
    }

    public function addHeader(Request\Parameter $header): self
    {
        $this->headers[] = $header;

        return $this;
    }

    public function addQuery(Request\Parameter $query): self
    {
        $this->queries[] = $query;

        return $this;
    }

    public function addBody(Request\Parameter $body): self
    {
        $this->body[] = $body;

        return $this;
    }
}
