<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;

final readonly class ServerConfiguration
{
    /**
     * @param array<string, array{default: string, enum?: list<string>, description?: string}> $variables
     * @param string|null $name written from 3.2 only
     */
    public function __construct(
        public string $url,
        public ?string $description = null,
        public array $variables = [],
        public ?string $name = null,
    ) {}

    /**
     * @param array<array-key, mixed> $config
     *
     * @throws InvalidConfigurationException
     */
    public static function fromArray(string $key, array $config): self
    {
        return new self('');
    }
}
