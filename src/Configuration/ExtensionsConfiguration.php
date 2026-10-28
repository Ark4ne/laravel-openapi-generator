<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;

/**
 * Classes the application adds to the registries, resolved through the container by the Laravel layer.
 */
final readonly class ExtensionsConfiguration
{
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
     * @throws InvalidConfigurationException when a class does not exist or does not implement its interface
     */
    public static function fromArray(string $key, array $config): self
    {
        return new self();
    }
}
