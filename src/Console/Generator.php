<?php

namespace Ark4ne\OpenApi\Console;

use Ark4ne\OpenApi\Documentation\DocumentationGenerator;
use Illuminate\Console\Command;

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
        foreach (config('openapi.versions') as $version => $config) {
            /** @var DocumentationGenerator $generator */
            $generator = app()->make(DocumentationGenerator::class);

            $generator->generate($version);
        }

        return 0;
    }
}
