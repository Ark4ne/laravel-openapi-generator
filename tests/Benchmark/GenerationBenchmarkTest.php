<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Benchmark;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Generation\Generator;
use Ark4ne\OpenApi\Laravel\OpenApiServiceProvider;
use Ark4ne\OpenApi\Output\DocumentValidator;
use Ark4ne\OpenApi\Output\DocumentWriter;
use Ark4ne\OpenApi\Tests\Fixtures\Requests\RequestController;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\ResponseController;
use Ark4ne\OpenApi\Tests\Unit\Sampling\SamplingTestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;

/**
 * `composer benchmark`: one document of 500 routes, generated, validated and written; the time and the peak memory
 * are printed.
 */
#[CoversNothing]
#[Group('benchmark')]
final class GenerationBenchmarkTest extends SamplingTestCase
{
    private const ROUTES = 500;

    private const ACTIONS = [
        [ResponseController::class, 'show', 'GET', '{article}'],
        [ResponseController::class, 'index', 'GET', ''],
        [ResponseController::class, 'paginated', 'GET', ''],
        [ResponseController::class, 'store', 'POST', ''],
        [ResponseController::class, 'model', 'GET', '{id}'],
        [ResponseController::class, 'shape', 'GET', 'shape'],
        [ResponseController::class, 'throwing', 'DELETE', '{id}'],
        [RequestController::class, 'store', 'POST', 'users'],
        [RequestController::class, 'search', 'GET', 'search'],
        [RequestController::class, 'update', 'PUT', '{post}'],
    ];

    /**
     * @param Application $app
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [OpenApiServiceProvider::class];
    }

    public function test_a_document_of_500_routes(): void
    {
        Storage::fake('local');

        for ($index = 0; $index < self::ROUTES; ++$index) {
            [$controller, $action, $method, $suffix] = self::ACTIONS[$index % count(self::ACTIONS)];
            $this->router()->match([$method], rtrim("api/resource{$index}/{$suffix}", '/'), [$controller, $action])->name("resource{$index}.{$action}")->middleware($index % 3 === 0 ? ['auth:sanctum', 'throttle:60,1'] : []);
        }

        $configuration = Configuration::fromArray(['documents' => ['v1' => ['info' => ['title' => 'API', 'version' => '1'], 'routes' => ['include' => null], 'strict' => false]]]);
        $generator = $this->app?->make(Generator::class);
        $writer = $this->app?->make(DocumentWriter::class);
        self::assertInstanceOf(Generator::class, $generator);
        self::assertInstanceOf(DocumentWriter::class, $writer);
        memory_reset_peak_usage();
        $start = hrtime(true);

        $generated = $generator->generate($configuration, 'v1');
        (new DocumentValidator())->validate($generated->openApi, $generated->context);
        $writer->write($generated->openApi, $generated->context);

        $seconds = (hrtime(true) - $start) / 1e9;
        fwrite(STDERR, sprintf("\n%d routes, %d operations: %.2f s, peak memory %.1f MB\n", self::ROUTES, count($generated->description->operations), $seconds, memory_get_peak_usage() / 1048576));

        self::assertCount(self::ROUTES, $generated->description->operations);
    }
}
