<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature\Laravel;

use Ark4ne\OpenApi\Laravel\Commands\GenerateCommand;
use Ark4ne\OpenApi\Laravel\DiagnosticsOutput;
use Ark4ne\OpenApi\Tests\Fixtures\Extensions\StampedMiddlewareMapper;
use Ark4ne\OpenApi\Tests\Fixtures\Extensions\UppercaseRuleMapper;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\ResponseController;
use Ark4ne\OpenApi\Tests\Fixtures\Types\UntypedController;
use Ark4ne\OpenApi\Tests\Support\SplitOutput;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(GenerateCommand::class)]
#[CoversClass(DiagnosticsOutput::class)]
final class GenerateCommandTest extends LaravelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('docs');
        $this->config()->set('openapi.output.disk', 'docs');
        $this->config()->set('openapi.documents.v1.routes.include', null);
        $this->config()->set('openapi.documents.v1.output', []);
        $this->config()->set('openapi.documents.admin', ['info' => ['title' => 'Admin', 'version' => '1'], 'routes' => ['include' => ['admin/*']]]);
        $this->router()->get('api/articles/{article}', [ResponseController::class, 'show'])->name('articles.show');
        $this->router()->get('api/ping', [UntypedController::class, 'ping'])->name('ping');
    }

    public function test_every_document_is_written(): void
    {
        $this->command('openapi:generate')
            ->expectsOutputToContain('openapi/v1.json')
            ->expectsOutputToContain('openapi/admin.json')
            ->assertExitCode(0);

        Storage::disk('docs')->assertExists(['openapi/v1.json', 'openapi/admin.json']);
        self::assertStringContainsString('"/api/articles/{article}"', (string) Storage::disk('docs')->get('openapi/v1.json'));
    }

    public function test_only_the_chosen_documents_are_written(): void
    {
        $this->command('openapi:generate', ['--document' => ['admin']])->assertExitCode(0);

        Storage::disk('docs')->assertExists('openapi/admin.json');
        Storage::disk('docs')->assertMissing('openapi/v1.json');
    }

    public function test_an_unknown_document_is_an_invalid_call(): void
    {
        $this->command('openapi:generate', ['--document' => ['v9']])
            ->expectsOutputToContain('openapi.documents: expected one of v1, admin, got "v9"')
            ->assertExitCode(2);
    }

    public function test_an_invalid_configuration_is_an_invalid_call(): void
    {
        $this->config()->set('openapi.documents.v1.openapi', '4.0');

        $this->command('openapi:generate')
            ->expectsOutputToContain('openapi.documents.v1.openapi: expected one of')
            ->assertExitCode(2);
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->command('openapi:generate', ['--dry-run' => true])->assertExitCode(0);

        Storage::disk('docs')->assertMissing('openapi/v1.json');
    }

    public function test_the_document_is_printed_on_the_standard_output(): void
    {
        $this->command('openapi:generate', ['--document' => ['v1'], '--stdout' => true])
            ->expectsOutputToContain('"openapi": "3.1.2"')
            ->assertExitCode(0);

        Storage::disk('docs')->assertMissing('openapi/v1.json');
    }

    public function test_the_standard_output_takes_one_document_only(): void
    {
        $this->command('openapi:generate', ['--stdout' => true])
            ->expectsOutputToContain('--stdout writes one document: choose it with --document')
            ->assertExitCode(2);
    }

    public function test_the_format_option_replaces_the_configured_one(): void
    {
        $this->command('openapi:generate', ['--document' => ['v1'], '--format' => 'yaml'])->assertExitCode(0);

        Storage::disk('docs')->assertExists('openapi/v1.yaml');
        self::assertStringStartsWith('openapi: 3.1.2', (string) Storage::disk('docs')->get('openapi/v1.yaml'));
    }

    public function test_an_unknown_format_is_an_invalid_call(): void
    {
        $this->command('openapi:generate', ['--format' => 'xml'])
            ->expectsOutputToContain('--format: expected one of json, yaml, got "xml"')
            ->assertExitCode(2);
    }

    public function test_the_warnings_are_grouped_by_route(): void
    {
        $this->router()->get('api/broken-author', [ResponseController::class, 'brokenAuthor']);

        $this->command('openapi:generate', ['--document' => ['v1']])
            ->expectsOutputToContain('broken author')
            ->doesntExpectOutputToContain('no return type')
            ->assertExitCode(0);
    }

    public function test_the_infos_are_shown_when_verbose(): void
    {
        $this->command('openapi:generate', ['--document' => ['v1'], '-v' => true])
            ->expectsOutputToContain('GET /api/ping')
            ->expectsOutputToContain('no return type, declare the responses with #[Response]')
            ->assertExitCode(0);
    }

    public function test_quiet_shows_the_errors_only(): void
    {
        $this->router()->get('api/broken-author', [ResponseController::class, 'brokenAuthor']);

        $this->command('openapi:generate', ['--document' => ['v1'], '-q' => true])
            ->doesntExpectOutputToContain('broken author')
            ->assertExitCode(0);

        $this->config()->set('openapi.documents.v1.openapi', '3.0.4');
        $this->config()->set('openapi.documents.v1.self', 'https://example.com/openapi.json');

        $this->command('openapi:generate', ['--document' => ['v1'], '-q' => true])
            ->expectsOutputToContain('$self')
            ->assertExitCode(1);
    }

    public function test_strict_fails_on_a_warning(): void
    {
        $this->router()->get('api/broken-author', [ResponseController::class, 'brokenAuthor']);

        $this->command('openapi:generate', ['--document' => ['v1'], '--strict' => true])
            ->expectsOutputToContain('broken author')
            ->assertExitCode(1);

        Storage::disk('docs')->assertMissing('openapi/v1.json');
    }

    public function test_an_error_fails_the_generation(): void
    {
        $this->config()->set('openapi.documents.v1.openapi', '3.0.4');
        $this->config()->set('openapi.documents.v1.strict', true);
        $this->config()->set('openapi.documents.v1.self', 'https://example.com/openapi.json');

        $this->command('openapi:generate', ['--document' => ['v1']])
            ->expectsOutputToContain('$self')
            ->assertExitCode(1);
    }

    public function test_a_class_of_the_wrong_kind_named_by_the_configuration_is_an_invalid_call(): void
    {
        $this->config()->set('openapi.documents.admin.strategies', ['summary' => ['by' => UppercaseRuleMapper::class]]);

        $this->command('openapi:generate')
            ->expectsOutputToContain('openapi.documents.admin.strategies.summary.by: expected one of')
            ->assertExitCode(2);

        Storage::disk('docs')->assertMissing('openapi/v1.json');
    }

    public function test_a_middleware_mapper_of_the_wrong_kind_is_an_invalid_call(): void
    {
        $this->config()->set('openapi.middlewares', ['tenant' => [UppercaseRuleMapper::class]]);

        $this->command('openapi:generate')
            ->expectsOutputToContain('openapi.middlewares.tenant: expected classes implementing')
            ->assertExitCode(2);
    }

    public function test_a_middleware_mapper_comes_from_the_container(): void
    {
        $this->config()->set('openapi.middlewares', ['tenant' => [StampedMiddlewareMapper::class]]);
        $this->router()->get('api/tenants', [UntypedController::class, 'ping'])->middleware('tenant');

        $this->command('openapi:generate', ['--document' => ['v1']])->assertExitCode(0);

        self::assertStringContainsString('"x-tenant": "stamped"', (string) Storage::disk('docs')->get('openapi/v1.json'));
    }

    public function test_with_stdout_the_standard_output_holds_the_document_only(): void
    {
        $output = new SplitOutput();

        self::assertSame(0, $this->kernel()->call('openapi:generate', ['--document' => ['v1'], '--stdout' => true, '--quiet' => true], $output));
        self::assertStringStartsWith('{', $output->standard());

        $this->config()->set('openapi.documents.v1.openapi', '3.0.4');
        $this->config()->set('openapi.documents.v1.self', 'https://example.com/openapi.json');
        $failed = new SplitOutput();

        self::assertSame(1, $this->kernel()->call('openapi:generate', ['--document' => ['v1'], '--stdout' => true], $failed));
        self::assertSame('', $failed->standard());
        self::assertStringContainsString('FAILED', $failed->error());
        self::assertStringContainsString('$self', $failed->error());
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
