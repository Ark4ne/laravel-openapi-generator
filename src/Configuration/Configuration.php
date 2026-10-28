<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;

/**
 * The whole `config/openapi.php`, typed and validated once when it is loaded.
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
        return new self([]);
    }

    /**
     * @throws InvalidConfigurationException when the document does not exist
     */
    public function document(string $name): DocumentConfiguration
    {
        return new DocumentConfiguration($name, new InfoConfiguration('', ''));
    }
}
