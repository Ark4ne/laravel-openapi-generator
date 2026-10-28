<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Laravel\Commands;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Configuration\OutputFormat;
use Ark4ne\OpenApi\Context\DiagnosticLevel;
use Ark4ne\OpenApi\Context\Diagnostics;
use Ark4ne\OpenApi\Exceptions\GenerationFailedException;
use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use Ark4ne\OpenApi\Generation\Generator;
use Ark4ne\OpenApi\Laravel\DiagnosticsOutput;
use Ark4ne\OpenApi\Output\DocumentValidator;
use Ark4ne\OpenApi\Output\DocumentWriter;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Generates the documents of the configuration, in each of its languages.
 *
 * Exit codes: 0 every document is written; 1 a document has an error, or a warning with `--strict` (it is not
 * written); 2 the configuration or the options are invalid (nothing is generated).
 */
#[AsCommand(name: 'openapi:generate')]
final class GenerateCommand extends Command
{
    protected $signature = 'openapi:generate
        {--document=* : Documents to generate, all of them by default}
        {--strict : Fail on the first warning}
        {--dry-run : Generate and validate, without writing}
        {--format= : json or yaml, instead of the configured format}
        {--stdout : Print the document instead of writing it (one document)}';

    protected $description = 'Generate the OpenAPI documents of the application';

    public function handle(): int
    {
        try {
            $configuration = $this->laravel->make(Configuration::class);
            $documents = $this->documents($configuration);
            $generator = $this->laravel->make(Generator::class);
        } catch (InvalidConfigurationException $exception) {
            $this->components->error($exception->getMessage());

            return self::INVALID;
        }

        $locales = $configuration->languages === [] ? [null] : $configuration->languages;

        if ($this->option('stdout') === true && count($documents) * count($locales) !== 1) {
            $this->components->error('--stdout writes one document: choose it with --document (and a single language)');

            return self::INVALID;
        }

        $writer = $this->laravel->make(DocumentWriter::class);
        $validator = $this->laravel->make(DocumentValidator::class);
        $failed = false;

        foreach ($documents as $document) {
            foreach ($locales as $locale) {
                $failed = ! $this->generate($generator, $writer, $validator, $configuration, $document, $locale) || $failed;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return bool whether the document is written (or would be, with `--dry-run`)
     */
    private function generate(Generator $generator, DocumentWriter $writer, DocumentValidator $validator, Configuration $configuration, DocumentConfiguration $document, ?string $locale): bool
    {
        $label = $locale === null ? $document->name : "{$document->name} ({$locale})";
        $stdout = $this->option('stdout') === true;
        $console = $this->output->getOutput();
        // With --stdout, the diagnostics go to the error output: the standard output holds the document only.
        $output = new DiagnosticsOutput($stdout && $console instanceof ConsoleOutputInterface ? $console->getErrorOutput() : $this->output);

        try {
            $generated = $generator->generate($configuration, $document, $locale);
            $contents = $writer->contents($generated->openApi, $generated->context);
            $validator->validate($generated->openApi, $generated->context);
        } catch (GenerationFailedException $exception) {
            $this->report($label, null, $output, $exception->diagnostics());

            return false;
        }

        $diagnostics = $generated->context->diagnostics;

        if ($this->fails($diagnostics)) {
            $this->report($label, null, $output, $diagnostics);

            return false;
        }

        if ($stdout) {
            $this->output->write($contents, false, OutputInterface::OUTPUT_RAW);
            $output->print($diagnostics);
        } else {
            $this->report($label, $this->option('dry-run') === true ? '<fg=yellow>dry run</>' : $writer->store($contents, $generated->context), $output, $diagnostics);
        }

        return true;
    }

    /**
     * @param string|null $result where the document is written, `null` when it is not
     */
    private function report(string $label, ?string $result, DiagnosticsOutput $output, Diagnostics $diagnostics): void
    {
        $this->components->twoColumnDetail("<options=bold>{$label}</>", $result ?? '<fg=red;options=bold>FAILED</>');
        $output->print($diagnostics);
    }

    private function fails(Diagnostics $diagnostics): bool
    {
        return $diagnostics->has(DiagnosticLevel::Error) || ($this->option('strict') === true && $diagnostics->has(DiagnosticLevel::Warning));
    }

    /**
     * The documents of `--document` (all by default), in the format of `--format`.
     *
     * @return list<DocumentConfiguration>
     *
     * @throws InvalidConfigurationException
     */
    private function documents(Configuration $configuration): array
    {
        $names = $this->option('document');
        $names = is_array($names) && $names !== [] ? array_values(array_filter($names, is_string(...))) : array_keys($configuration->documents);
        $format = $this->option('format');
        $format = is_string($format) ? $this->format($format) : null;
        $documents = [];

        foreach ($names as $name) {
            $document = $configuration->document($name);
            $documents[] = $format === null ? $document : $document->withFormat($format);
        }

        return $documents;
    }

    /**
     * @throws InvalidConfigurationException
     */
    private function format(string $format): OutputFormat
    {
        return OutputFormat::tryFrom($format) ?? throw InvalidConfigurationException::forOption('format', 'one of json, yaml', $format);
    }
}
