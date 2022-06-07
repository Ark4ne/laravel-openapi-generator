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
    ) {
    }

    /**
     * @return array<string, Request\Security>
     */
    public function securities(): array { return $this->securities; }

    /**
     * @return array<string, Request\Parameter>
     */
    public function parameters(): array { return $this->parameters; }

    /**
     * @return array<string, Request\Parameter>
     */
    public function headers(): array { return $this->headers; }

    /**
     * @return array<string, Request\Parameter>
     */
    public function body(): array { return $this->body; }

    /**
     * @return array<string, Request\Parameter>
     */
    public function queries(): array { return $this->queries; }
}
