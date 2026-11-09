<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Generation;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Generation\GeneratedDocument;
use Ark4ne\OpenApi\Generation\Generator;
use Ark4ne\OpenApi\Generation\GeneratorExtensions;
use Ark4ne\OpenApi\Generation\Integration;
use Ark4ne\OpenApi\Tests\Feature\DocumentScenario;
use Ark4ne\OpenApi\Tests\Fixtures\Preparation\FailingPass;
use Ark4ne\OpenApi\Tests\Fixtures\Preparation\NotePass;
use Ark4ne\OpenApi\Tests\Fixtures\Preparation\PreparedController;
use Ark4ne\OpenApi\Tests\Fixtures\Preparation\PreparedRequest;
use Ark4ne\OpenApi\Tests\Fixtures\Preparation\TestDocumentIntegration;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApiWriter\Enums\Version;
use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * What a generation gives the document integrations: their settings, the sample requests, the form requests whose
 * rules are read, the description, the passes.
 */
#[CoversClass(Generator::class)]
final class GeneratorDocumentIntegrationsTest extends DocumentScenario
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->router()->get('api/prepared', [PreparedController::class, 'show']);
        $this->router()->post('api/prepared', [PreparedController::class, 'store']);
    }

    public function test_the_resources_are_rendered_on_a_plain_request_without_integration(): void
    {
        self::assertSame('none', $this->example($this->written($this->preparingGenerator()->generate($this->configuration(), 'v1'))));
    }

    public function test_the_available_integrations_prepare_the_sample_requests_in_their_order(): void
    {
        $generator = $this->preparingGenerator(new TestDocumentIntegration('first'), new TestDocumentIntegration('unavailable', available: false), new TestDocumentIntegration('second'));

        self::assertSame('first,second', $this->example($this->written($generator->generate($this->configuration(), 'v1'))));
    }

    public function test_the_integrations_prepare_the_form_requests(): void
    {
        $written = $this->written($this->preparingGenerator(new TestDocumentIntegration('first'))->generate($this->configuration(), 'v1'));

        self::assertSame(['name', 'prepared_first'], array_keys((array) Schemas::at($written, 'paths', '/api/prepared', 'post', 'requestBody', 'content', 'application/json', 'schema', 'properties')));
    }

    public function test_an_integration_describes_the_document(): void
    {
        $generator = $this->preparingGenerator(new TestDocumentIntegration('first', description: 'First'), new TestDocumentIntegration('silent'), new TestDocumentIntegration('unavailable', available: false, description: 'Never'), new TestDocumentIntegration('second', description: 'Second'));

        $written = $this->written($generator->generate($this->configuration(), 'v1'));

        self::assertSame("The API.\n\nFirst\n\nSecond", Schemas::at($written, 'info', 'description'));
    }

    public function test_a_failing_integration_is_reported_and_the_others_kept(): void
    {
        $generator = $this->preparingGenerator(new TestDocumentIntegration('broken', failing: true), new TestDocumentIntegration('second', description: 'Second'));

        $generated = $generator->generate($this->configuration(), 'v1');
        $diagnostics = array_map(strval(...), $generated->context->diagnostics->all());

        self::assertSame("The API.\n\nSecond", Schemas::at($this->written($generated), 'info', 'description'));
        self::assertContains('[warning] pass: the integration broken cannot describe the document v1: broken cannot describe', $diagnostics);
        self::assertCount(1, array_keys($diagnostics, '[warning] sampling: cannot prepare a sample request for the integration broken: broken cannot prepare', true));
        self::assertContains('[warning] request POST /api/prepared: cannot prepare the request ' . PreparedRequest::class . ' for the integration broken: broken cannot prepare', $diagnostics);
    }

    public function test_the_document_is_described_inside_the_sampling_session(): void
    {
        $session = $this->samplingSession();
        $persisting = [];
        $integration = new TestDocumentIntegration('recording', onDescribe: static function () use ($session, &$persisting): void {
            $persisting[] = $session->canPersist();
        });
        $container = $this->app?->make(Container::class);
        self::assertInstanceOf(Container::class, $container);

        Generator::make($this->router(), $container, $session, integrations: [$integration])->generate($this->configuration(), 'v1');

        self::assertSame([true], $persisting);
    }

    public function test_a_document_without_description_from_integrations_keeps_its_own(): void
    {
        $written = $this->written($this->preparingGenerator(new TestDocumentIntegration('silent'))->generate($this->configuration(), 'v1'));

        self::assertSame('The API.', Schemas::at($written, 'info', 'description'));
    }

    public function test_an_integration_reads_its_settings_in_the_document(): void
    {
        $generator = $this->preparingGenerator(new TestDocumentIntegration('noted'), new TestDocumentIntegration('other'));

        $written = $this->written($generator->generate($this->configuration(['noted' => ['note' => 'From the settings.']]), 'v1'));

        self::assertSame("The API.\n\nFrom the settings.", Schemas::at($written, 'info', 'description'));
    }

    public function test_the_passes_of_the_integrations_run_before_those_of_the_application(): void
    {
        $generator = $this->extendedGenerator(
            new GeneratorExtensions(passes: [new NotePass('application pass')]),
            new TestDocumentIntegration('first', passes: [new NotePass('first pass')]),
            new TestDocumentIntegration('unavailable', available: false, passes: [new NotePass('never')]),
            new TestDocumentIntegration('second', passes: [new NotePass('second pass')]),
        );

        $written = $this->written($generator->generate($this->configuration(), 'v1'));

        self::assertSame("The API.\n\nfirst pass\n\nsecond pass\n\napplication pass", Schemas::at($written, 'info', 'description'));
    }

    public function test_an_integration_that_cannot_give_its_passes_is_reported(): void
    {
        $generator = $this->preparingGenerator(new TestDocumentIntegration('broken', failing: true), new TestDocumentIntegration('second', passes: [new NotePass('second pass')]));

        $generated = $generator->generate($this->configuration(), 'v1');

        self::assertSame("The API.\n\nsecond pass", Schemas::at($this->written($generated), 'info', 'description'));
        self::assertContains('[warning] pass: cannot add the passes of the integration broken: broken cannot give its passes', array_map(strval(...), $generated->context->diagnostics->all()));
    }

    public function test_a_failing_pass_of_an_integration_is_reported_and_the_document_kept(): void
    {
        $generator = $this->preparingGenerator(new TestDocumentIntegration('broken', passes: [new NotePass('before'), new FailingPass(), new NotePass('after')]));

        $generated = $generator->generate($this->configuration(), 'v1');

        self::assertSame("The API.\n\nbefore\n\nafter", Schemas::at($this->written($generated), 'info', 'description'));
        self::assertContains('[warning] pass: the pass ' . FailingPass::class . ' of the integration broken failed, left out: settings of an unexpected shape', array_map(strval(...), $generated->context->diagnostics->all()));
    }

    public function test_what_is_not_a_pass_is_reported_and_left_out(): void
    {
        $generator = $this->preparingGenerator(new TestDocumentIntegration('sloppy', passes: ['App\\Passes\\Stamp', new NotePass('kept')]));

        $generated = $generator->generate($this->configuration(), 'v1');

        self::assertSame("The API.\n\nkept", Schemas::at($this->written($generated), 'info', 'description'));
        self::assertContains('[warning] pass: the integration sloppy gives a pass that does not implement Ark4ne\OpenApi\Generation\Pass, left out: string', array_map(strval(...), $generated->context->diagnostics->all()));
    }

    public function test_a_later_integration_with_the_key_of_another_is_left_out(): void
    {
        $generator = $this->preparingGenerator(new TestDocumentIntegration('same', description: 'First'), new TestDocumentIntegration('same', description: 'Second'));

        $generated = $generator->generate($this->configuration(), 'v1');

        self::assertSame("The API.\n\nFirst", Schemas::at($this->written($generated), 'info', 'description'));
        self::assertContains('[warning] configuration: the integration ' . TestDocumentIntegration::class . ' has the key same of ' . TestDocumentIntegration::class . ', left out', array_map(strval(...), $generated->context->diagnostics->all()));
    }

    public function test_settings_no_available_integration_reads_are_reported(): void
    {
        $generator = $this->preparingGenerator(new TestDocumentIntegration('noted'), new TestDocumentIntegration('unavailable', available: false));

        $generated = $generator->generate($this->configuration(['noted' => [], 'unavailable' => [], 'typo' => []]), 'v1');

        self::assertSame([
            '[warning] configuration: no available integration reads documents.v1.integrations.unavailable, settings ignored',
            '[warning] configuration: no available integration reads documents.v1.integrations.typo, settings ignored',
        ], array_values(array_filter(array_map(strval(...), $generated->context->diagnostics->all()), static fn(string $diagnostic): bool => str_contains($diagnostic, 'configuration:'))));
    }

    private function preparingGenerator(Integration ...$integrations): Generator
    {
        return $this->extendedGenerator(new GeneratorExtensions(), ...$integrations);
    }

    private function extendedGenerator(GeneratorExtensions $extensions, Integration ...$integrations): Generator
    {
        $container = $this->app?->make(Container::class);
        self::assertInstanceOf(Container::class, $container);

        return Generator::make($this->router(), $container, $this->samplingSession(), integrations: array_values($integrations), extensions: $extensions);
    }

    /**
     * @param array<string, array<string, mixed>> $integrations
     */
    private function configuration(array $integrations = []): Configuration
    {
        return Configuration::fromArray(['documents' => ['v1' => [
            'info' => ['title' => 'API', 'version' => '1', 'description' => 'The API.'],
            'routes' => ['include' => null],
            'integrations' => $integrations,
        ]]]);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function written(GeneratedDocument $generated): array
    {
        return $generated->openApi->toArray(Version::V3_1_2);
    }

    /**
     * @param array<array-key, mixed> $written
     */
    private function example(array $written): mixed
    {
        return Schemas::at($written, 'components', 'schemas', 'Prepared', 'properties', 'prepared', 'example');
    }
}
