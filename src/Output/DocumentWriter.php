<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Output;

use Ark4ne\OpenApi\Configuration\OutputFormat;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Exceptions\GenerationFailedException;
use Ark4ne\OpenApiWriter\Exceptions\UnsupportedFeatureException;
use Ark4ne\OpenApiWriter\Objects\OpenApi;
use Ark4ne\OpenApiWriter\Serialization\Diagnostic;
use Ark4ne\OpenApiWriter\Serialization\DiagnosticLevel;
use Ark4ne\OpenApiWriter\Serialization\Serializer;
use Illuminate\Contracts\Filesystem\Factory;

/**
 * Writes a document with the writer, in the version, the mode and with the vendor extensions of its configuration,
 * as readable JSON or as YAML, on the disk of `output`.
 *
 * What the writer converts or drops for the version becomes a diagnostic; a strict writing that fails becomes a
 * GenerationFailedException carrying every diagnostic.
 */
final readonly class DocumentWriter
{
    public function __construct(
        private Factory $disks,
    ) {}

    /**
     * Writes the document and returns its path on the disk.
     *
     * @throws GenerationFailedException
     */
    public function write(OpenApi $document, GenerationContext $context): string
    {
        return $this->store($this->contents($document, $context), $context);
    }

    /**
     * Stores contents already written (`contents()`) at the path of the document and returns that path.
     */
    public function store(string $contents, GenerationContext $context): string
    {
        $path = $this->path($context);
        $this->disks->disk($context->document->output->disk)->put($path, $contents);

        return $path;
    }

    /**
     * @throws GenerationFailedException
     */
    public function contents(OpenApi $document, GenerationContext $context): string
    {
        $configuration = $context->document;

        try {
            $result = Serializer::for($configuration->version, $configuration->strictness, $configuration->vendorExtensions)->serialize($document);
        } catch (UnsupportedFeatureException $exception) {
            foreach ($exception->diagnostics() as $diagnostic) {
                $context->diagnostics->error(Stage::Writing, (string) $diagnostic);
            }

            throw GenerationFailedException::fromDiagnostics($configuration->name, $context->diagnostics);
        }

        foreach ($result->diagnostics() as $diagnostic) {
            self::report($diagnostic, $context);
        }

        return $configuration->output->format === OutputFormat::Yaml
            ? $result->toYaml()
            : $result->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * `directory/file` of `output`, `{document}`, `{locale}` and `{variant}` replaced; a locale is added before the
     * extension when the file name has no `{locale}`, a variant (or the name of the head) when it has no `{variant}`.
     */
    public function path(GenerationContext $context): string
    {
        $output = $context->document->output;
        $locale = $context->locale;
        $variant = $context->variantName();
        $file = $output->file;

        // The variant always comes before the locale: inserted before `.{locale}`, else before the extension, the
        // locale then going between the variant and the extension.
        if ($variant !== null && ! str_contains($file, '{variant}')) {
            $file = str_contains($file, '.{locale}') ? str_replace('.{locale}', '.{variant}.{locale}', $file) : self::beforeExtension($file, '{variant}');
        }

        if ($locale !== null && ! str_contains($file, '{locale}')) {
            $file = self::beforeExtension($file, '{locale}');
        }

        $file = str_replace(['{document}', '{locale}', '{variant}'], [$context->document->name, $locale ?? '', $variant ?? ''], $file);

        return trim($output->directory, '/') === '' ? $file : trim($output->directory, '/') . '/' . $file;
    }

    /**
     * `$file` with `.$placeholder` before its extension (at its end without extension).
     */
    private static function beforeExtension(string $file, string $placeholder): string
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        return $extension === '' ? "{$file}.{$placeholder}" : substr($file, 0, -strlen($extension) - 1) . ".{$placeholder}.{$extension}";
    }

    private static function report(Diagnostic $diagnostic, GenerationContext $context): void
    {
        match ($diagnostic->level) {
            DiagnosticLevel::Converted => $context->diagnostics->info(Stage::Writing, (string) $diagnostic),
            default => $context->diagnostics->warning(Stage::Writing, (string) $diagnostic),
        };
    }
}
