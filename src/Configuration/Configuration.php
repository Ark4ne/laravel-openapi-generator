<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;

/**
 * The whole `config/openapi.php`, typed and validated once when it is loaded.
 *
 * Read so far: the documents (see DocumentConfiguration), `servers`, `output`, `sampling`, `date_formats`, `languages`
 * and `exceptions`; `middlewares` and `extensions` come with the service provider (E10).
 *
 * Nothing outside the Laravel layer reads `config()`: every component receives this object, or the
 * DocumentConfiguration of the document being generated.
 */
final readonly class Configuration
{
    /**
     * @param array<string, DocumentConfiguration> $documents
     * @param array<string, list<class-string>> $middlewares middleware name or class → middleware mappers
     * @param array<string, string> $dateFormats PHP date format → OpenAPI format (`date`, `date-time`)
     * @param list<string> $languages
     */
    public function __construct(
        public array $documents,
        public SamplingConfiguration $sampling = new SamplingConfiguration(),
        public array $middlewares = [],
        public ExtensionsConfiguration $extensions = new ExtensionsConfiguration(),
        public array $dateFormats = [],
        public array $languages = [],
        public ExceptionsConfiguration $exceptions = new ExceptionsConfiguration(),
    ) {}

    /**
     * @param array<array-key, mixed> $config the `openapi` configuration
     *
     * @throws InvalidConfigurationException with the key, the expected value and the received one
     */
    public static function fromArray(array $config): self
    {
        $entries = $config['documents'] ?? [];

        if (! is_array($entries)) {
            throw InvalidConfigurationException::forKey('documents', 'a map of documents', $entries);
        }

        $servers = [];

        foreach (ConfigurationValues::array('servers', $config['servers'] ?? []) as $index => $server) {
            $servers[] = ServerConfiguration::fromArray("servers.{$index}", ConfigurationValues::array("servers.{$index}", $server));
        }

        $output = ConfigurationValues::array('output', $config['output'] ?? []);
        OutputConfiguration::fromArray('output', $output);
        $documents = [];

        foreach ($entries as $name => $entry) {
            if (! is_array($entry)) {
                throw InvalidConfigurationException::forKey("documents.{$name}", 'an array', $entry);
            }

            $documents[(string) $name] = DocumentConfiguration::fromArray((string) $name, $entry, $output, $servers);
        }

        $exceptions = $config['exceptions'] ?? [];

        if (! is_array($exceptions)) {
            throw InvalidConfigurationException::forKey('exceptions', 'an array', $exceptions);
        }

        $sampling = $config['sampling'] ?? [];

        if (! is_array($sampling)) {
            throw InvalidConfigurationException::forKey('sampling', 'an array', $sampling);
        }

        $dateFormats = $config['date_formats'] ?? [];

        if (! is_array($dateFormats) || array_filter($dateFormats, is_string(...)) !== $dateFormats || array_is_list($dateFormats) && $dateFormats !== []) {
            throw InvalidConfigurationException::forKey('date_formats', 'a map of PHP date format to OpenAPI format', $dateFormats);
        }

        /** @var array<string, string> $dateFormats */
        return new self(
            $documents,
            SamplingConfiguration::fromArray('sampling', $sampling),
            dateFormats: $dateFormats,
            languages: ConfigurationValues::strings('languages', $config['languages'] ?? []),
            exceptions: ExceptionsConfiguration::fromArray('exceptions', $exceptions),
        );
    }

    /**
     * @throws InvalidConfigurationException when the document does not exist
     */
    public function document(string $name): DocumentConfiguration
    {
        return $this->documents[$name] ?? throw InvalidConfigurationException::forKey('documents', 'one of ' . implode(', ', array_keys($this->documents)), $name);
    }
}
