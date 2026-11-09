<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Generation;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Generation\GeneratedDocument;
use Ark4ne\OpenApi\Generation\Generator;
use Ark4ne\OpenApi\Generation\Integration;
use Ark4ne\OpenApi\Tests\Feature\DocumentScenario;
use Ark4ne\OpenApi\Tests\Fixtures\Preparation\PreparedController;
use Ark4ne\OpenApi\Tests\Fixtures\Preparation\PreparedRequest;
use Ark4ne\OpenApi\Tests\Fixtures\Preparation\TestPreparingIntegration;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApiWriter\Enums\Version;
use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * What a generation gives the integrations that prepare requests and describe the document: the sample requests, the
 * form requests whose rules are read, the description.
 */
#[CoversClass(Generator::class)]
final class GeneratorPreparationTest extends DocumentScenario
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
        $generator = $this->preparingGenerator(new TestPreparingIntegration('first'), new TestPreparingIntegration('unavailable', available: false), new TestPreparingIntegration('second'));

        self::assertSame('first,second', $this->example($this->written($generator->generate($this->configuration(), 'v1'))));
    }

    public function test_the_integrations_prepare_the_form_requests(): void
    {
        $written = $this->written($this->preparingGenerator(new TestPreparingIntegration('first'))->generate($this->configuration(), 'v1'));

        self::assertSame(['name', 'prepared_first'], array_keys((array) Schemas::at($written, 'paths', '/api/prepared', 'post', 'requestBody', 'content', 'application/json', 'schema', 'properties')));
    }

    public function test_an_integration_describes_the_document(): void
    {
        $generator = $this->preparingGenerator(new TestPreparingIntegration('first', description: 'First'), new TestPreparingIntegration('silent'), new TestPreparingIntegration('unavailable', available: false, description: 'Never'), new TestPreparingIntegration('second', description: 'Second'));

        $written = $this->written($generator->generate($this->configuration(), 'v1'));

        self::assertSame("The API.\n\nFirst\n\nSecond", Schemas::at($written, 'info', 'description'));
    }

    public function test_a_failing_integration_is_reported_and_the_others_kept(): void
    {
        $generator = $this->preparingGenerator(new TestPreparingIntegration('broken', failing: true), new TestPreparingIntegration('second', description: 'Second'));

        $generated = $generator->generate($this->configuration(), 'v1');
        $diagnostics = array_map(strval(...), $generated->context->diagnostics->all());

        self::assertSame("The API.\n\nSecond", Schemas::at($this->written($generated), 'info', 'description'));
        self::assertContains('[warning] pass: cannot describe the document v1: broken cannot describe', $diagnostics);
        self::assertCount(1, array_keys($diagnostics, '[warning] sampling: cannot prepare a sample request: broken cannot prepare', true));
        self::assertContains('[warning] request POST /api/prepared: cannot prepare the request ' . PreparedRequest::class . ': broken cannot prepare', $diagnostics);
    }

    public function test_the_document_is_described_inside_the_sampling_session(): void
    {
        $session = $this->samplingSession();
        $persisting = [];
        $integration = new TestPreparingIntegration('recording', onDescribe: static function () use ($session, &$persisting): void {
            $persisting[] = $session->canPersist();
        });
        $container = $this->app?->make(Container::class);
        self::assertInstanceOf(Container::class, $container);

        Generator::make($this->router(), $container, $session, integrations: [$integration])->generate($this->configuration(), 'v1');

        self::assertSame([true], $persisting);
    }

    public function test_a_document_without_description_from_integrations_keeps_its_own(): void
    {
        $written = $this->written($this->preparingGenerator(new TestPreparingIntegration('silent'))->generate($this->configuration(), 'v1'));

        self::assertSame('The API.', Schemas::at($written, 'info', 'description'));
    }

    private function preparingGenerator(Integration ...$integrations): Generator
    {
        $container = $this->app?->make(Container::class);
        self::assertInstanceOf(Container::class, $container);

        return Generator::make($this->router(), $container, $this->samplingSession(), integrations: array_values($integrations));
    }

    private function configuration(): Configuration
    {
        return Configuration::fromArray(['documents' => ['v1' => [
            'info' => ['title' => 'API', 'version' => '1', 'description' => 'The API.'],
            'routes' => ['include' => null],
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
