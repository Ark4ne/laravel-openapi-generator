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
        return new self();
    }
}
