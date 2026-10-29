<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;

/**
 * Classes the application adds to the registries, resolved through the container by the Laravel layer, which checks
 * that each one implements the interface of its registry.
 */
final readonly class ExtensionsConfiguration
{
    public const KEYS = ['operation_extractors', 'request_extractors', 'rule_mappers', 'response_extractors', 'samplers', 'passes'];

    /**
     * @param list<class-string> $operationExtractors
     * @param list<class-string> $requestExtractors
     * @param list<class-string> $ruleMappers
     * @param list<class-string> $responseExtractors
     * @param list<class-string> $samplers
     * @param list<class-string> $passes
     */
    public function __construct(
        public array $operationExtractors = [],
        public array $requestExtractors = [],
        public array $ruleMappers = [],
        public array $responseExtractors = [],
        public array $samplers = [],
        public array $passes = [],
    ) {}

    /**
     * @param array<array-key, mixed> $config
     *
     * @throws InvalidConfigurationException when a key is unknown or a class does not exist
     */
    public static function fromArray(string $key, array $config): self
    {
        ConfigurationValues::keys($key, $config, self::KEYS);

        return new self(
            ConfigurationValues::classes("{$key}.operation_extractors", $config['operation_extractors'] ?? []),
            ConfigurationValues::classes("{$key}.request_extractors", $config['request_extractors'] ?? []),
            ConfigurationValues::classes("{$key}.rule_mappers", $config['rule_mappers'] ?? []),
            ConfigurationValues::classes("{$key}.response_extractors", $config['response_extractors'] ?? []),
            ConfigurationValues::classes("{$key}.samplers", $config['samplers'] ?? []),
            ConfigurationValues::classes("{$key}.passes", $config['passes'] ?? []),
        );
    }
}
