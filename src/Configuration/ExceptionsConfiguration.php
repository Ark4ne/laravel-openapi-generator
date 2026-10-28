<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;

/**
 * How the exceptions an action declares become responses (`exceptions`).
 */
final readonly class ExceptionsConfiguration
{
    /**
     * @param bool $readThrows read the `@throws` of the actions
     * @param array<class-string, int|array<string, mixed>> $map exception class → its status, or `status` and
     *        `description`, for the exceptions the application cannot annotate; an entry without status is ignored
     */
    public function __construct(
        public bool $readThrows = true,
        public array $map = [],
    ) {}

    /**
     * @param array<array-key, mixed> $config
     *
     * @throws InvalidConfigurationException
     */
    public static function fromArray(string $key, array $config): self
    {
        foreach (array_keys($config) as $name) {
            if (! in_array($name, ['read_throws', 'map'], true)) {
                throw InvalidConfigurationException::forKey($key, 'the keys read_throws, map', $name);
            }
        }

        $readThrows = $config['read_throws'] ?? true;

        if (! is_bool($readThrows)) {
            throw InvalidConfigurationException::forKey("{$key}.read_throws", 'a boolean', $readThrows);
        }

        $map = $config['map'] ?? [];

        if (! is_array($map) || ($map !== [] && array_is_list($map))) {
            throw InvalidConfigurationException::forKey("{$key}.map", 'a map of exception class to response', $map);
        }

        /** @var array<class-string, int|array<string, mixed>> $map an entry without status is ignored by its mapper */
        return new self($readThrows, $map);
    }
}
