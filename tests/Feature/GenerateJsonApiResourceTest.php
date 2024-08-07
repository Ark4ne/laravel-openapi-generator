<?php

namespace Test\Feature;

use Ark4ne\OpenApi\Support\Facades\Logger;
use Test\app\Http\Controllers\CommentController;
use Test\app\Http\Controllers\PostController;
use Test\app\Http\Controllers\UserController;
use Test\Concerns\AssertOpenApi;
use Test\Support\Reflect;

class GenerateJsonApiResourceTest extends FeatureTestCase
{
    use AssertOpenApi;

    protected function defineRoutes($router)
    {
        include __DIR__ . '/../app/routes_json_api.php';
    }

    public function testGenerateJsonApiResource(): void
    {
        $config = $this->app['config']['openapi'];
        $file = "{$config['output-dir']}/{$config['versions']['v1']['output-file']}";

        // Logger::interceptor(static fn(string $message, bool $newline) => fwrite(STDOUT, strip_tags($message . ($newline ? PHP_EOL : ''))));

        $this
            ->artisan('openapi:generate --force')
            ->assertSuccessful();

        $this->assertOpenapiFileIs(__DIR__ . '/expected/openapi-jsonapi.json', $file);
        $this->assertOpenapiFile($file);
    }
}
