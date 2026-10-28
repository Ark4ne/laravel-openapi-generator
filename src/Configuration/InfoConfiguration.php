<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;

/**
 * The `info` object of a document.
 */
final readonly class InfoConfiguration
{
    /**
     * @param array{name?: string, url?: string, email?: string} $contact
     * @param array{name?: string, identifier?: string, url?: string} $license
     */
    public function __construct(
        public string $title,
        public string $version,
        public ?string $summary = null,
        public ?string $description = null,
        public ?string $termsOfService = null,
        public array $contact = [],
        public array $license = [],
    ) {}

    /**
     * @param array<array-key, mixed> $config
     *
     * @throws InvalidConfigurationException
     */
    public static function fromArray(string $key, array $config): self
    {
        return new self('', '');
    }
}
