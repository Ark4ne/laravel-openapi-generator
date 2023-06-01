<?php

namespace Test\Feature;

use Ark4ne\OpenApi\Support\Facades\Logger;
use Test\app\Http\Controllers\CommentController;
use Test\app\Http\Controllers\PostController;
use Test\app\Http\Controllers\UserController;
use Test\Concerns\AssertOpenApi;
use Test\Support\Reflect;

class GenerateTest extends FeatureTestCase
{
    use AssertOpenApi;

    public function testGenerate(): void
    {
        Reflect::set(CommentController::class, 'useJsonApiResource', false);
        Reflect::set(UserController::class, 'useJsonApiResource', false);
        Reflect::set(PostController::class, 'useJsonApiResource', false);

        $config = $this->app['config']['openapi'];
        $file = "{$config['output-dir']}/{$config['versions']['v1']['output-file']}";

        Logger::interceptor(static fn(string $message, bool $newline) => fwrite(STDOUT, strip_tags($message . ($newline ? PHP_EOL : ''))));

        $this
            ->artisan('openapi:generate --force')
            ->assertSuccessful();

        $this->assertOpenapiFileIs(__DIR__ . '/expected/openapi-jsonresource.json', $file);
        $this->assertOpenapiFile($file);
    }

    public function testGenerateJsonApiResource(): void
    {
        Reflect::set(CommentController::class, 'useJsonApiResource', true);
        Reflect::set(UserController::class, 'useJsonApiResource', true);
        Reflect::set(PostController::class, 'useJsonApiResource', true);

        $config = $this->app['config']['openapi'];
        $file = "{$config['output-dir']}/{$config['versions']['v1']['output-file']}";

        Logger::interceptor(static fn(string $message, bool $newline) => fwrite(STDOUT, strip_tags($message . ($newline ? PHP_EOL : ''))));

        $this
            ->artisan('openapi:generate --force')
            ->assertSuccessful();

        $this->assertOpenapiFileIs(__DIR__ . '/expected/openapi-jsonapi.json', $file);
        $this->assertOpenapiFile($file);
    }
}
