<?php

namespace Test\Feature;

use Ark4ne\OpenApi\Support\Facades\Logger;
use Illuminate\Support\Facades\Storage;
use Test\Concerns\AssertOpenApi;

class GenerateJsonResourceTest extends FeatureTestCase
{
    use AssertOpenApi;

    protected function defineRoutes($router)
    {
        include __DIR__ . '/../app/routes.php';
    }

    public function testGenerateJsonResource(): void
    {
        $config = $this->app['config']['openapi'];
        $file = Storage::disk($config['output-disk'])->path("{$config['output-dir']}/{$config['versions']['v1']['output-file']}");

        // Logger::interceptor(static fn(string $message, bool $newline) => fwrite(STDOUT, strip_tags($message . ($newline ? PHP_EOL : ''))));

        $this
            ->artisan('openapi:generate --force')
            ->assertSuccessful();

        $this->assertOpenapiFileIs(__DIR__ . '/expected/openapi-jsonresource.json', $file);
        $this->assertOpenapiFile($file);
    }
}
