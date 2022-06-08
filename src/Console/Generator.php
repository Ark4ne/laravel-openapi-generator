<?php

namespace Ark4ne\OpenApi\Console;

use Ark4ne\OpenApi\Documentation\DocumentationGenerator;
use Closure;
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
        try {
            $this->beginTransaction();

            foreach (config('openapi.versions') as $version => $config) {
                /** @var DocumentationGenerator $generator */
                $generator = app()->make(DocumentationGenerator::class);

                $generator->generate($version);
            }
        } finally {
            $this->rollback();
        }

        return 0;
    }

    protected function beginTransaction(): bool
    {
        if (!config('openapi.use-transaction')) {
            return false;
        }
        $connections = array_keys(config('database.connections'));

        foreach ($connections as $connection) {
            try {
                $connect = DB::connection($connection);
            } catch (\Throwable $e) {
                $this->info("$connection unreachable");
                continue;
            }
            try {
                $connect->beginTransaction();
            } catch (\Throwable $e) {
                $this->info("$connection can't start transaction");
            }
        }

        return true;
    }

    protected function rollback(): bool
    {
        if (!config('openapi.use-transaction')) {
            return false;
        }
        $connections = array_keys(config('database.connections'));

        foreach (array_reverse($connections) as $connection) {
            try {
                $connect = DB::connection($connection);
            } catch (\Throwable $e) {
                continue;
            }
            try {
                $connect->rollBack();
            } catch (\Throwable $e) {
                $this->info("$connection can't rollback transaction");
            }
        }

        return true;
    }
}
