<?php

namespace Test\Support;

use Ark4ne\OpenApi\Providers\LaravelOpenApiProvider;
use Orchestra\Testbench\Concerns\CreatesApplication;

trait UseLocalApp
{
    protected function getPackageProviders($app)
    {
        return [
            LaravelOpenApiProvider::class
        ];
    }

    protected function defineRoutes($router)
    {
        include __DIR__ . '/../app/routes.php';
    }

    protected function defineEnvironment($app)
    {
        $app['config']['openapi'] = include __DIR__ . '/../app/openapi.php';
    }
}
