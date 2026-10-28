<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;

/**
 * How resources are sampled: transactions, size of the sampled collections, Faker seed, examples.
 */
final readonly class SamplingConfiguration
{
    /**
     * @param list<string> $connections drivers whose connections are wrapped in a rolled back transaction
     */
    public function __construct(
        public bool $transactions = true,
        public array $connections = ['mariadb', 'mysql', 'pgsql', 'sqlite', 'sqlsrv'],
        public int $collectionSize = 2,
        public int $seed = 1,
        public bool $examples = true,
    ) {}

    /**
     * @param array<array-key, mixed> $config
     *
     * @throws InvalidConfigurationException
     */
    public static function fromArray(string $key, array $config): self
    {
        $defaults = new self();

        foreach (array_keys($config) as $name) {
            if (! in_array($name, ['transactions', 'connections', 'collection_size', 'seed', 'examples'], true)) {
                throw InvalidConfigurationException::forKey($key, 'the keys transactions, connections, collection_size, seed, examples', $name);
            }
        }

        return new self(
            self::boolean("{$key}.transactions", $config['transactions'] ?? $defaults->transactions),
            ConfigurationValues::strings("{$key}.connections", $config['connections'] ?? $defaults->connections),
            self::integer("{$key}.collection_size", $config['collection_size'] ?? $defaults->collectionSize, 1),
            self::integer("{$key}.seed", $config['seed'] ?? $defaults->seed, 0),
            self::boolean("{$key}.examples", $config['examples'] ?? $defaults->examples),
        );
    }

    private static function boolean(string $key, mixed $value): bool
    {
        return is_bool($value) ? $value : throw InvalidConfigurationException::forKey($key, 'a boolean', $value);
    }

    private static function integer(string $key, mixed $value, int $minimum): int
    {
        return is_int($value) && $value >= $minimum ? $value : throw InvalidConfigurationException::forKey($key, "an integer of at least {$minimum}", $value);
    }
}
