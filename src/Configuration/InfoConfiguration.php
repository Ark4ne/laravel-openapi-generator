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
        $contact = ConfigurationValues::array("{$key}.contact", $config['contact'] ?? []);
        $license = ConfigurationValues::array("{$key}.license", $config['license'] ?? []);

        /** @var array{name?: string, url?: string, email?: string} $contact */
        $contact = array_filter($contact, is_string(...));
        /** @var array{name?: string, identifier?: string, url?: string} $license */
        $license = array_filter($license, is_string(...));

        return new self(
            ConfigurationValues::string("{$key}.title", $config['title'] ?? null),
            ConfigurationValues::string("{$key}.version", $config['version'] ?? '1.0.0'),
            ConfigurationValues::nullableString("{$key}.summary", $config['summary'] ?? null),
            ConfigurationValues::nullableString("{$key}.description", $config['description'] ?? null),
            ConfigurationValues::nullableString("{$key}.terms_of_service", $config['terms_of_service'] ?? null),
            $contact,
            $license,
        );
    }
}
