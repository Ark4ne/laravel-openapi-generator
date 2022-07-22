<?php

namespace Ark4ne\OpenApi\Console;

use Ark4ne\OpenApi\Documentation\DocumentationGenerator;
use Ark4ne\OpenApi\Errors\Log;
use Ark4ne\OpenApi\Support\Config;
use Ark4ne\OpenApi\Support\Trans;
use Ark4ne\OpenApi\Support\Translator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Generator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openapi:generate';

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

        Log::interseptor(fn(string $level, string $context, string $message) => $this->$level("[$context] - $message"));

        try {
            if (!$this->beginTransaction()) {
                return 0;
            }

            foreach (Config::versions() as $version => $config) {
                Config::version($version);

                if (!empty($languages = Config::languages())) {
                    foreach ($languages as $language) {
                        Trans::lang($language);

                        /** @var DocumentationGenerator $generator */
                        $generator = app()->make(DocumentationGenerator::class);
                        $generator->generate($version, $language);
                    }
                } else {
                    /** @var DocumentationGenerator $generator */
                    $generator = app()->make(DocumentationGenerator::class);
                    $generator->generate($version);
                }
            }
        } finally {
            $this->rollback();
        }

        return 0;
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

        return $success || $this->confirm("Some connections could not establish a transaction. Do you want to continue ?");
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
