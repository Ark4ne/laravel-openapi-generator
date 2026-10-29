<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature\Laravel;

use Ark4ne\OpenApi\Laravel\OpenApiServiceProvider;
use Ark4ne\OpenApi\Tests\Unit\Sampling\SamplingTestCase;
use Illuminate\Foundation\Application;
use Illuminate\Testing\PendingCommand;

/**
 * An application with the service provider of the package, its configuration published as it is.
 */
abstract class LaravelTestCase extends SamplingTestCase
{
    /**
     * @param Application $app
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [OpenApiServiceProvider::class];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function command(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);
        self::assertInstanceOf(PendingCommand::class, $pending);

        return $pending;
    }
}
