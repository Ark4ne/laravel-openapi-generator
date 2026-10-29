<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature;

use Ark4ne\OpenApiWriter\Objects\Info;
use Ark4ne\OpenApiWriter\Objects\OpenApi;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class EnvironmentTest extends TestCase
{
    public function test_the_testing_application_runs_laravel_13(): void
    {
        self::assertStringStartsWith('13.', $this->app?->version() ?? '');
    }

    public function test_the_testing_application_uses_an_in_memory_sqlite_database(): void
    {
        self::assertSame('sqlite', DB::connection()->getDriverName());
        self::assertSame(':memory:', DB::connection()->getConfig('database'));
    }

    public function test_the_openapi_writer_is_installed(): void
    {
        $document = OpenApi::create()->info(Info::create()->title('API')->version('1.0.0'));

        self::assertSame(['title' => 'API', 'version' => '1.0.0'], $document->toArray()['info'] ?? null);
    }
}
