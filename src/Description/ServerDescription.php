<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Description;

/**
 * A server of an operation: the domain of its route (`https://{account}.example.com`) and its variables.
 */
final readonly class ServerDescription extends Description
{
    /**
     * @param array<string, array{default: string, enum?: list<string>, description?: string}> $variables
     */
    protected function __construct(
        public string $url,
        public ?string $description = null,
        public array $variables = [],
    ) {}

    public static function create(string $url): self
    {
        return new self($url);
    }

    public function description(?string $description): static
    {
        return $this->copy(description: $description);
    }

    /**
     * @param list<string> $enum
     */
    public function variable(string $name, string $default, array $enum = [], ?string $description = null): static
    {
        $variable = ['default' => $default];

        if ($enum !== []) {
            $variable['enum'] = $enum;
        }

        if ($description !== null) {
            $variable['description'] = $description;
        }

        return $this->copy(variables: [...$this->variables, $name => $variable]);
    }
}
