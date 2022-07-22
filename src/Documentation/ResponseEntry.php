<?php

namespace Ark4ne\OpenApi\Documentation;

use GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType;

class ResponseEntry
{

    /**
     * @param string                                                                                                    $format
     * @param int                                                                                                       $statusCode
     * @param array<string, \GoldSpecDigital\ObjectOrientedOAS\Objects\Header>                                          $headers
     * @param \Ark4ne\OpenApi\Documentation\Request\Parameter|\GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType|null $body
     */
    public function __construct(
        protected string $format,
        protected int $statusCode = 0,
        protected string $statusName = '',
        protected array $headers = [],
        protected null|Request\Parameter|MediaType $body = null,
    ) {
    }

    /**
     * @return string
     */
    public function format(): string { return $this->format; }

    /**
     * @return int
     */
    public function statusCode(): int { return $this->statusCode; }

    /**
     * @return string
     */
    public function statusName(): string { return $this->statusName; }

    /**
     * @return array<string, \GoldSpecDigital\ObjectOrientedOAS\Objects\Header>
     */
    public function headers(): array { return $this->headers; }

    /**
     * @return \Ark4ne\OpenApi\Documentation\Request\Parameter|\GoldSpecDigital\ObjectOrientedOAS\Objects\MediaType|null
     */
    public function body(): null|Request\Parameter|MediaType { return $this->body; }
}
