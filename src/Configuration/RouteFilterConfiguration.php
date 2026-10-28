<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;

/**
 * Which routes a document contains: shell masks on the URI (`api/*`) and HTTP methods left out.
 */
final readonly class RouteFilterConfiguration
{
    /**
     * @param list<string> $include
     * @param list<string> $exclude
     * @param list<string> $ignoredMethods upper case
     */
    public function __construct(
        public array $include = ['api/*'],
        public array $exclude = [],
        public array $ignoredMethods = ['HEAD'],
    ) {}

    /**
     * @param array<array-key, mixed> $config
     *
     * @throws InvalidConfigurationException
     */
    public static function fromArray(string $key, array $config): self
    {
        return new self();
    }
}
