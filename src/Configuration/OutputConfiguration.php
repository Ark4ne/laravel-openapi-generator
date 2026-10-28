<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;

/**
 * Where a document is written. `file` accepts the `{document}` and `{locale}` placeholders.
 */
final readonly class OutputConfiguration
{
    public function __construct(
        public ?string $disk = null,
        public string $directory = 'openapi',
        public string $file = '{document}.json',
        public OutputFormat $format = OutputFormat::Json,
    ) {}

    /**
     * @param array<array-key, mixed> $config the global `output` merged with the document's own
     *
     * @throws InvalidConfigurationException
     */
    public static function fromArray(string $key, array $config): self
    {
        $defaults = new self();

        return new self(
            ConfigurationValues::nullableString("{$key}.disk", $config['disk'] ?? null),
            ConfigurationValues::string("{$key}.directory", $config['directory'] ?? $defaults->directory),
            ConfigurationValues::string("{$key}.file", $config['file'] ?? $defaults->file),
            ConfigurationValues::enum("{$key}.format", $config['format'] ?? $defaults->format->value, OutputFormat::class),
        );
    }
}
