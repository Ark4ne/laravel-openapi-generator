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
        $by = $config['by'] ?? $default;

        if (! is_string($by) || $by === '') {
            throw InvalidConfigurationException::forKey("{$key}.by", 'a strategy name or class', $by);
        }

        $options = [];

        foreach ($config as $name => $value) {
            if ($name !== 'by') {
                $options[(string) $name] = $value;
            }
        }

        $pattern = $options['pattern'] ?? null;

        if ($pattern !== null && (! is_string($pattern) || @preg_match($pattern, '') === false)) {
            throw InvalidConfigurationException::forKey("{$key}.pattern", 'a regular expression', $pattern);
        }

        return new self($by, $options);
    }

    /**
     * The `pattern` option: its first group, or its whole match, gives the value.
     */
    public function pattern(): ?string
    {
        $pattern = $this->options['pattern'] ?? null;

        return is_string($pattern) ? $pattern : null;
    }
}
