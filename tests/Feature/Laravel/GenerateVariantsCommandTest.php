<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature\Laravel;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Generation\GeneratedDocument;
use Ark4ne\OpenApi\Generation\Generator;
use Ark4ne\OpenApi\Laravel\Commands\GenerateCommand;
use Ark4ne\OpenApi\Output\DocumentWriter;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\ResponseController;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\ApiRevision;
use Ark4ne\OpenApi\Tests\Support\SplitOutput;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * `openapi:generate` on a document with variants (`documents.<name>.variants`): one document per variant, then the
 * head.
 */
#[CoversClass(GenerateCommand::class)]
#[CoversClass(Generator::class)]
#[CoversClass(DocumentWriter::class)]
final class GenerateVariantsCommandTest extends LaravelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('docs');
        $this->config()->set('openapi.output.disk', 'docs');
        $this->config()->set('openapi.documents.v1.routes.include', null);
        $this->config()->set('openapi.documents.v1.info.version', '{variant}');
        $this->config()->set('openapi.documents.v1.output', ['file' => '{document}-{variant}.json']);
        $this->config()->set('openapi.documents.v1.variants', ['values' => ApiRevision::class, 'head' => 'latest']);
        $this->config()->set('openapi.documents.admin', ['info' => ['title' => 'Admin', 'version' => '1'], 'routes' => ['include' => ['admin/*']]]);
        $this->router()->get('api/articles/{article}', [ResponseController::class, 'show'])->name('articles.show');
    }

    public function test_a_document_is_generated_once_per_variant_and_for_the_head(): void
    {
        $this->command('openapi:generate')->assertExitCode(0);

        Storage::disk('docs')->assertExists(['openapi/v1-2026-09-01.json', 'openapi/v1-2026-12-01.json', 'openapi/v1-latest.json', 'openapi/admin.json']);
        Storage::disk('docs')->assertMissing(['openapi/v1-.json', 'openapi/v1.json']);
    }

    public function test_a_document_without_head_is_generated_once_per_variant(): void
    {
        $this->config()->set('openapi.documents.v1.variants.head', false);

        $this->command('openapi:generate', ['--document' => ['v1']])->assertExitCode(0);

        Storage::disk('docs')->assertExists(['openapi/v1-2026-09-01.json', 'openapi/v1-2026-12-01.json']);
        Storage::disk('docs')->assertMissing('openapi/v1-latest.json');
    }

    public function test_the_label_of_a_generation_names_its_variant(): void
    {
        $this->command('openapi:generate', ['--document' => ['v1']])
            ->expectsOutputToContain('v1 [2026-09-01]')
            ->expectsOutputToContain('v1 [2026-12-01]')
            ->expectsOutputToContain('v1 [latest]')
            ->assertExitCode(0);
    }

    public function test_the_label_names_the_variant_before_the_locale(): void
    {
        $this->config()->set('openapi.languages', ['en']);

        $this->command('openapi:generate', ['--document' => ['v1']])
            ->expectsOutputToContain('v1 [2026-12-01] (en)')
            ->assertExitCode(0);
    }

    public function test_the_info_version_names_the_variant(): void
    {
        $this->command('openapi:generate', ['--document' => ['v1']])->assertExitCode(0);

        self::assertSame('2026-12-01', $this->info('openapi/v1-2026-12-01.json')['version'] ?? null);
        self::assertSame('latest', $this->info('openapi/v1-latest.json')['version'] ?? null);
    }

    public function test_the_variant_option_generates_one_variant(): void
    {
        $this->command('openapi:generate', ['--document' => ['v1'], '--variant' => '2026-12-01'])->assertExitCode(0);

        Storage::disk('docs')->assertExists('openapi/v1-2026-12-01.json');
        Storage::disk('docs')->assertMissing(['openapi/v1-2026-09-01.json', 'openapi/v1-latest.json']);
    }

    public function test_the_variant_option_generates_the_head(): void
    {
        $this->command('openapi:generate', ['--document' => ['v1'], '--variant' => 'latest'])->assertExitCode(0);

        Storage::disk('docs')->assertExists('openapi/v1-latest.json');
        Storage::disk('docs')->assertMissing(['openapi/v1-2026-09-01.json', 'openapi/v1-2026-12-01.json']);
    }

    public function test_an_unknown_variant_option_fails(): void
    {
        $this->command('openapi:generate', ['--document' => ['v1'], '--variant' => '2027-01-01'])
            ->expectsOutputToContain('--variant: expected one of 2026-09-01, 2026-12-01, latest, got "2027-01-01"')
            ->assertExitCode(2);

        Storage::disk('docs')->assertMissing(['openapi/v1-2026-09-01.json', 'openapi/v1-2026-12-01.json', 'openapi/v1-latest.json']);
    }

    public function test_the_variant_option_needs_documents_with_variants(): void
    {
        $this->command('openapi:generate', ['--variant' => '2026-12-01'])
            ->expectsOutputToContain('--variant: expected one of')
            ->assertExitCode(2);
    }

    public function test_the_standard_output_takes_one_variant_only(): void
    {
        $this->command('openapi:generate', ['--document' => ['v1'], '--stdout' => true])
            ->expectsOutputToContain('--stdout writes one document')
            ->assertExitCode(2);

        $output = new SplitOutput();

        self::assertSame(0, $this->kernel()->call('openapi:generate', ['--document' => ['v1'], '--variant' => '2026-12-01', '--stdout' => true, '--quiet' => true], $output));
        self::assertStringContainsString('"version": "2026-12-01"', $output->standard());
    }

    public function test_every_generation_of_the_generator_carries_its_variant(): void
    {
        $generator = $this->app?->make(Generator::class);
        self::assertInstanceOf(Generator::class, $generator);
        $config = $this->config()->get('openapi');
        self::assertIsArray($config);

        $generated = $generator->all(Configuration::fromArray($config));
        $variants = array_map(static fn(GeneratedDocument $document): string => $document->context->document->name . ':' . ($document->context->variant->name ?? 'head'), $generated);

        self::assertSame(['v1:2026-09-01', 'v1:2026-12-01', 'v1:head', 'admin:head'], $variants);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function info(string $path): array
    {
        $document = json_decode((string) Storage::disk('docs')->get($path), true);
        self::assertIsArray($document);
        self::assertIsArray($document['info'] ?? null);

        return $document['info'];
    }

    private function kernel(): Kernel
    {
        $kernel = $this->app?->make(Kernel::class);
        self::assertInstanceOf(Kernel::class, $kernel);

        return $kernel;
    }

    private function config(): Repository
    {
        $config = $this->app?->make(Repository::class);
        self::assertInstanceOf(Repository::class, $config);

        return $config;
    }
}
