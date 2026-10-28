<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;

/**
 * A naming strategy: a built-in keyword (`controller`, `uri`, `name`…) or a class implementing the strategy
 * interface, with its options (`pattern`…).
 */
final readonly class StrategyConfiguration
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public string $by,
        public array $options = [],
    ) {}

    /**
     * @param array<array-key, mixed> $config `['by' => 'controller', 'pattern' => '/…/']`
     *
     * @throws InvalidConfigurationException
     */
    public static function fromArray(string $key, array $config, string $default): self
    {
        return new self($default);
    }
}
