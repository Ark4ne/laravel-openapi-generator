<?php

namespace Ark4ne\OpenApi\Console;

use Ark4ne\OpenApi\Documentation\DocumentationGenerator;
use Ark4ne\OpenApi\Support\Config;
use Ark4ne\OpenApi\Support\Facades\Logger;
use Ark4ne\OpenApi\Support\Trans;
use Ark4ne\OpenApi\Support\Translator;
use GoldSpecDigital\ObjectOrientedOAS\Exceptions\ValidationException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Generator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openapi:generate {--force}';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'OpenAPI generator';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate OpenAPI spec.';

    public function handle(): int
    {
        $this->translator();

        Logger::interceptor(fn(string $message, bool $newline) => $this->output->write($message, $newline));

        try {
            if (!$this->beginTransaction()) {
                return 0;
            }

            foreach (Config::versions() as $version => $config) {
                Config::version($version);

                if (!empty($languages = Config::languages())) {
                    foreach ($languages as $language) {
                        Trans::lang($language);
                        $this->generate($version, $language);
                    }
                } else {
                    $this->generate($version);
                }
            }
        } finally {
            $this->rollback();
        }

        $this->line('');

        return 0;
    }

    protected function generate(string $version, string $lang = null): void
    {
        Logger::start("Generate: $version" . ($lang ? " - $lang" : ''));
        /** @var DocumentationGenerator $generator */
        $generator = app()->make(DocumentationGenerator::class);
        $openapi = $generator->generate($version);
        Logger::end('success');

        try {
            Logger::start("Validate: $version" . ($lang ? " - $lang" : ''));
            $openapi->validate();
            Logger::end('success');
        } catch (ValidationException $exception) {
            $errors = collect($exception->getErrors())->groupBy('pointer');
            foreach ($errors as $pointer => $error) {
                Logger::start($pointer);
                foreach ($error as $e) {
                    Logger::error("[{$e['constraint']}] {$e['message']}");
                }
                Logger::end();
            }
            Logger::end('error', 'validation failed with ' . count($exception->getErrors()) . " errors");
        }

        if (!is_dir($dir = Config::outputDir()) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        $path = Config::outputFile();

        $file = $lang ? "$lang-$path" : $path;

        file_put_contents("$dir/$file", $openapi->toJson());
    }

    protected function beginTransaction(): bool
    {
        if (!Config::connections('use-transaction')) {
            return false;
        }
        $connections = array_keys(config('database.connections'));

        $success = true;

        foreach ($connections as $connection) {
            try {
                $connect = DB::connection($connection);
            } catch (\Throwable $e) {
                $this->comment("$connection unreachable");
                continue;
            }
            try {
                $connect->beginTransaction();
            } catch (\Throwable $e) {
                $success = false;
                $this->comment("$connection can't start transaction");
            }
        }

        return $success
            || $this->option('force')
            || $this->confirm("Some connections could not establish a transaction. Do you want to continue ?");
    }

    protected function rollback(): bool
    {
        if (!Config::connections('use-transaction')) {
            return true;
        }

        $connections = array_keys(config('database.connections'));

        $success = true;

        foreach (array_reverse($connections) as $connection) {
            try {
                $connect = DB::connection($connection);
            } catch (\Throwable $e) {
                continue;
            }
            try {
                $connect->rollBack();
            } catch (\Throwable $e) {
                $this->warn("$connection can't rollback transaction");
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Overload translator
     *
     * @return void
     */
    protected function translator(): void
    {
        app()->singleton('translator', function ($app) {
            $loader = $app['translation.loader'];

            // When registering the translator component, we'll need to set the default
            // locale as well as the fallback locale. So, we'll grab the application
            // configuration so we can easily get both of these values from there.
            $locale = $app['config']['app.locale'];

            $trans = new Translator($loader, $locale);

            $trans->setFallback($app['config']['app.fallback_locale']);

            return $trans;
        });
    }
}
