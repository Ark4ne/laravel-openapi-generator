<?php

namespace Ark4ne\OpenApi\Providers;

use Ark4ne\OpenApi\Console\Generator;
use Illuminate\Support\ServiceProvider;

class LaravelOpenApiProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Generator::class
            ]);
        }

        $this->mergeConfigFrom(__DIR__ . '/../../config/openapi.php', 'openapi');
    }

    public function register()
    {
        $this->publishes([
            __DIR__ . '/../../config/openapi.php' => config_path('openapi.php')
        ], 'config');
    }
}
