<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Generation;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\Variant;
use Ark4ne\OpenApi\Generation\GeneratedDocument;
use Ark4ne\OpenApi\Generation\Generator;
use Ark4ne\OpenApi\Generation\Integration;
use Ark4ne\OpenApi\Tests\Feature\DocumentScenario;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\ApiRevision;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\RevisionController;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\RevisionRequest;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\TestVariantIntegration;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApiWriter\Enums\Version;
use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * What a generation of a variant gives the application and the variant integrations: its sample requests, its
 * description.
 */
#[CoversClass(Generator::class)]
final class GeneratorVariantsTest extends DocumentScenario
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->router()->get('api/revision', [RevisionController::class, 'show']);
        $this->router()->post('api/revision', [RevisionController::class, 'store']);
    }

    public function test_the_resources_are_rendered_on_a_request_of_the_variant(): void
    {
        $written = $this->written($this->variantGenerator()->generate($this->configuration(), 'v1', variant: $this->labels()));

        self::assertSame('2026-12-01', $this->example($written, 'revision'));
    }

    public function test_the_resources_of_the_head_are_rendered_without_header(): void
    {
        $written = $this->written($this->variantGenerator()->generate($this->configuration(), 'v1'));

        self::assertSame('none', $this->example($written, 'revision'));
    }

    public function test_an_integration_prepares_the_sample_requests_of_a_variant(): void
    {
        $generator = $this->variantGenerator(new TestVariantIntegration('first'), new TestVariantIntegration('unavailable', available: false), new TestVariantIntegration('second'));

        $written = $this->written($generator->generate($this->configuration(), 'v1', variant: $this->labels()));

        self::assertSame('first:2026-12-01,second:2026-12-01', $this->example($written, 'prepared'));
    }

    public function test_the_rules_of_a_form_request_are_read_on_a_request_of_the_variant(): void
    {
        $variant = $this->written($this->variantGenerator()->generate($this->configuration(), 'v1', variant: $this->labels()));
        $head = $this->written($this->variantGenerator()->generate($this->configuration(), 'v1'));

        self::assertSame(['label'], array_keys((array) Schemas::at($variant, 'paths', '/api/revision', 'post', 'requestBody', 'content', 'application/json', 'schema', 'properties')));
        self::assertSame(['name'], array_keys((array) Schemas::at($head, 'paths', '/api/revision', 'post', 'requestBody', 'content', 'application/json', 'schema', 'properties')));
    }

    public function test_an_integration_prepares_the_form_requests_of_a_variant(): void
    {
        $written = $this->written($this->variantGenerator(new TestVariantIntegration('first'))->generate($this->configuration(), 'v1', variant: $this->labels()));

        self::assertSame(['label', 'prepared_first'], array_keys((array) Schemas::at($written, 'paths', '/api/revision', 'post', 'requestBody', 'content', 'application/json', 'schema', 'properties')));
    }

    public function test_an_integration_describes_the_variant_in_the_document(): void
    {
        $generator = $this->variantGenerator(new TestVariantIntegration('first', description: 'Labels replace names'), new TestVariantIntegration('silent'), new TestVariantIntegration('unavailable', available: false, description: 'Never'), new TestVariantIntegration('second', description: 'Second'));

        $written = $this->written($generator->generate($this->configuration(), 'v1', variant: $this->labels()));

        self::assertSame("The API.\n\nLabels replace names (2026-12-01)\n\nSecond (2026-12-01)", Schemas::at($written, 'info', 'description'));
    }

    public function test_a_failing_description_is_reported_and_the_others_kept(): void
    {
        $generator = $this->variantGenerator(new TestVariantIntegration('broken', failing: true), new TestVariantIntegration('second', description: 'Second'));

        $generated = $generator->generate($this->configuration(), 'v1', variant: $this->labels());

        self::assertSame("The API.\n\nSecond (2026-12-01)", Schemas::at($this->written($generated), 'info', 'description'));
        self::assertContains('[warning] pass: cannot describe the variant 2026-12-01: broken cannot describe', array_map(strval(...), $generated->context->diagnostics->all()));
        $diagnostics = array_map(strval(...), $generated->context->diagnostics->all());
        self::assertCount(1, array_keys($diagnostics, '[warning] sampling: cannot prepare a sample request: broken cannot prepare', true));
        self::assertContains('[warning] request POST /api/revision: cannot prepare the request ' . RevisionRequest::class . ': broken cannot prepare', $diagnostics);
    }

    public function test_the_variant_is_described_inside_the_sampling_session(): void
    {
        $session = $this->samplingSession();
        $persisting = [];
        $integration = new TestVariantIntegration('recording', onDescribe: static function () use ($session, &$persisting): void {
            $persisting[] = $session->canPersist();
        });
        $container = $this->app?->make(Container::class);
        self::assertInstanceOf(Container::class, $container);

        Generator::make($this->router(), $container, $session, integrations: [$integration])->generate($this->configuration(), 'v1', variant: $this->labels());

        self::assertSame([true], $persisting);
    }

    public function test_a_document_without_description_from_integrations_keeps_its_own(): void
    {
        $written = $this->written($this->variantGenerator(new TestVariantIntegration('silent'))->generate($this->configuration(), 'v1', variant: $this->labels()));

        self::assertSame('The API.', Schemas::at($written, 'info', 'description'));
    }

    public function test_the_operations_of_a_variant_document_its_header(): void
    {
        $written = $this->written($this->variantGenerator()->generate($this->configuration(), 'v1', variant: $this->labels()));

        self::assertSame([['name' => 'Api-Version', 'in' => 'header', 'schema' => ['const' => '2026-12-01', 'type' => 'string', 'example' => '2026-12-01']]], Schemas::at($written, 'paths', '/api/revision', 'get', 'parameters'));
    }

    private function variantGenerator(Integration ...$integrations): Generator
    {
        $container = $this->app?->make(Container::class);
        self::assertInstanceOf(Container::class, $container);

        return Generator::make($this->router(), $container, $this->samplingSession(), integrations: array_values($integrations));
    }

    private function configuration(): Configuration
    {
        return Configuration::fromArray(['documents' => ['v1' => [
            'info' => ['title' => 'API', 'version' => '{variant}', 'description' => 'The API.'],
            'routes' => ['include' => null],
            'variants' => ['values' => ApiRevision::class, 'head' => 'latest', 'header' => 'Api-Version'],
        ]]]);
    }

    private function labels(): Variant
    {
        $variant = $this->configuration()->document('v1')->generation('2026-12-01');
        self::assertNotNull($variant);

        return $variant;
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
    private function example(array $written, string $property): mixed
    {
        return Schemas::at($written, 'components', 'schemas', 'Revision', 'properties', $property, 'example');
    }
}
