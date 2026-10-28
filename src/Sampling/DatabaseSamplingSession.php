<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Configuration\SamplingConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Throwable;

/**
 * Opens a transaction on each given connection whose driver is in `sampling.connections`, runs the generation and
 * rolls everything back, even when the generation fails. A connection that cannot be reached is reported and
 * sampled without transaction (`make()` only).
 *
 * The seed `sampling.seed` is set with `mt_srand()` for the session, which Faker relies on: two sessions give the
 * same values.
 */
final class DatabaseSamplingSession implements SamplingSession
{
    /** @var array<string, ConnectionInterface> connection name → connection in a transaction */
    private array $transactions = [];

    /**
     * @param list<string> $connections names of the connections of the application
     */
    public function __construct(
        private readonly ConnectionResolverInterface $resolver,
        private readonly array $connections,
        private readonly SamplingConfiguration $configuration,
    ) {}

    public function run(Closure $callback, GenerationContext $context): mixed
    {
        mt_srand($this->configuration->seed);

        try {
            if ($this->configuration->transactions) {
                $this->begin($context);
            }

            return $callback();
        } finally {
            $this->rollBack();
            mt_srand();
        }
    }

    public function canPersist(?string $connection = null): bool
    {
        return isset($this->transactions[$connection ?? $this->resolver->getDefaultConnection()]);
    }

    private function begin(GenerationContext $context): void
    {
        foreach ($this->connections as $name) {
            try {
                $connection = $this->resolver->connection($name);

                if ($connection instanceof Connection && in_array($connection->getDriverName(), $this->configuration->connections, true)) {
                    $connection->beginTransaction();
                    $this->transactions[$name] = $connection;
                }
            } catch (Throwable $error) {
                $context->diagnostics->warning(Stage::Sampling, "connection {$name} unreachable, sampled without transaction: {$error->getMessage()}");
            }
        }
    }

    private function rollBack(): void
    {
        foreach ($this->transactions as $connection) {
            try {
                $connection->rollBack();
            } catch (Throwable) {
                // A connection lost during the generation has nothing left to roll back.
            }
        }

        $this->transactions = [];
    }
}
