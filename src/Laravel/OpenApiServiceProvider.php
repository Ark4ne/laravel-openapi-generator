<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Laravel;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Generation\Generator;
use Ark4ne\OpenApi\Generation\GeneratorExtensions;
use Ark4ne\OpenApi\Integrations\JsonApi\JsonApiIntegration;
use Ark4ne\OpenApi\Laravel\Commands\GenerateCommand;
use Ark4ne\OpenApi\Laravel\Commands\InspectCommand;
use Composer\InstalledVersions;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the generator: its configuration (merged with `config/openapi.php`, published with
 * `vendor:publish --tag=openapi-config`), the generator built from it (GeneratorFactory), its commands and its
 * `about` entry. The commands read the configuration once and build their generator from it.
 *
 * The configuration is read when a command runs, not when the application boots: an invalid entry fails the
 * command, never the application.
 */
final class OpenApiServiceProvider extends ServiceProvider
{
    private const CONFIG = __DIR__ . '/../../config/openapi.php';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG, 'openapi');

        $this->app->bind(Configuration::class, static fn(): Configuration => Configuration::fromArray(self::config()));
        $this->app->bind(GeneratorExtensions::class, static fn(Container $app): GeneratorExtensions => (new ExtensionsResolver($app))->resolve($app->make(Configuration::class)->extensions));
        $this->app->bind(Generator::class, static fn(Container $app): Generator => $app->make(GeneratorFactory::class)->make($app->make(Configuration::class)));
    }

    public function boot(): void
    {
        $this->publishes([self::CONFIG => config_path('openapi.php')], 'openapi-config');

        if ($this->app->runningInConsole()) {
            $this->commands([GenerateCommand::class, InspectCommand::class]);
        }

        AboutCommand::add('OpenAPI Generator', static function (): array {
            $documents = self::config()['documents'] ?? null;

            return [
                'Version' => InstalledVersions::isInstalled('ark4ne/laravel-openapi-generator') ? (string) InstalledVersions::getPrettyVersion('ark4ne/laravel-openapi-generator') : 'dev',
                'Documents' => implode(', ', array_map(strval(...), array_keys(is_array($documents) ? $documents : []))),
                'JSON:API' => (new JsonApiIntegration())->available() ? '<fg=green;options=bold>ENABLED</>' : 'not installed',
            ];
        });
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function config(): array
    {
        $config = config('openapi');

        return is_array($config) ? $config : [];
    }
}
