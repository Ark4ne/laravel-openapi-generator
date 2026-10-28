<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Laravel;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Generation\Generator;
use Ark4ne\OpenApi\Generation\GeneratorExtensions;
use Ark4ne\OpenApi\Integrations\BuiltInIntegrations;
use Ark4ne\OpenApi\Integrations\JsonApi\JsonApiIntegration;
use Ark4ne\OpenApi\Laravel\Commands\GenerateCommand;
use Ark4ne\OpenApi\Laravel\Commands\InspectCommand;
use Ark4ne\OpenApi\Sampling\DatabaseSamplingSession;
use Composer\InstalledVersions;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the generator: its configuration (merged with `config/openapi.php`, published with
 * `vendor:publish --tag=openapi-config`), the generator built from it, its commands and its `about` entry.
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
        $this->app->bind(Generator::class, static function (Container $app): Generator {
            $configuration = $app->make(Configuration::class);
            $default = config('database.default');
            // The default connection: the others may not be reachable where the documents are generated.
            $session = new DatabaseSamplingSession($app->make(ConnectionResolverInterface::class), is_string($default) ? [$default] : [], $configuration->sampling);
            $translator = $app->bound(Translator::class) ? $app->make(Translator::class) : null;

            return Generator::make($app->make(Router::class), $app, $session, $translator, BuiltInIntegrations::all($configuration->integrations->jsonApiErrors), $app->make(GeneratorExtensions::class));
        });
    }

    public function boot(): void
    {
        $this->publishes([self::CONFIG => config_path('openapi.php')], 'openapi-config');

        if ($this->app->runningInConsole()) {
            $this->commands([GenerateCommand::class, InspectCommand::class]);
        }

        AboutCommand::add('OpenAPI Generator', static fn(): array => [
            'Version' => InstalledVersions::isInstalled('ark4ne/laravel-openapi-generator') ? (string) InstalledVersions::getPrettyVersion('ark4ne/laravel-openapi-generator') : 'dev',
            'Documents' => implode(', ', array_map(strval(...), array_keys(is_array(self::config()['documents'] ?? null) ? self::config()['documents'] : []))),
            'JSON:API' => (new JsonApiIntegration())->available() ? '<fg=green;options=bold>ENABLED</>' : 'not installed',
        ]);
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
