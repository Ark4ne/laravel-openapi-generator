<?php

namespace Test\Feature;

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
        $config = $this->app['config']['openapi'];
        $file = "{$config['output-dir']}/{$config['versions']['v1']['output-file']}";

        $this
            ->artisan('openapi:generate --force')
            ->assertSuccessful();

        $this->assertOpenapiFile($file);
    }

    public function testGenerateJsonApiResource(): void
    {
        Reflect::set(CommentController::class, 'useJsonApiResource', true);
        Reflect::set(UserController::class, 'useJsonApiResource', true);
        Reflect::set(PostController::class, 'useJsonApiResource', true);

        $config = $this->app['config']['openapi'];
        $file = "{$config['output-dir']}/{$config['versions']['v1']['output-file']}";

        $this
            ->artisan('openapi:generate --force')
            ->assertSuccessful();

        $this->assertOpenapiFile($file);
    }
}
