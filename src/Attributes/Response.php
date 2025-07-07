<?php

namespace Ark4ne\OpenApi\Attributes;


use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Response
{
    /**
     * @param int $statusCode
     * @param null|string $statusText
     * @param array<string, string|string[]> $headers
     * @param bool $paginated
     * @param null|string $description
     */
    public function __construct(
        public int         $statusCode = 200,
        public null|string $statusText = null,
        public array       $headers = [],
        public bool        $paginated = false,
        public null|string $description = null,
    )
    {
        if ($this->statusCode === null && $this->statusText !== null) {
            if (in_array($this->statusText, \Symfony\Component\HttpFoundation\Response::$statusTexts)) {
                $this->statusCode = array_search($this->statusText, \Symfony\Component\HttpFoundation\Response::$statusTexts);
            } else {
                throw new \InvalidArgumentException("Status code must be provided if status text is set.");
            }
        }
    }
}