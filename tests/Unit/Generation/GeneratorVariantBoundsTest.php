<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Generation;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Generation\Generator;
use Ark4ne\OpenApi\Generation\Passes\UnusedComponentsPass;
use Ark4ne\OpenApi\Generation\Passes\VariantBoundsPass;
use Ark4ne\OpenApi\Tests\Feature\DocumentScenario;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\ApiRevision;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\LabelController;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApiWriter\Enums\Version;
use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Each document of a variant lists the operations its bounds cover, and the components they use only.
 */
#[CoversClass(VariantBoundsPass::class)]
#[CoversClass(UnusedComponentsPass::class)]
final class GeneratorVariantBoundsTest extends DocumentScenario
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->router()->get('api/label', [LabelController::class, 'show']);
        $this->router()->get('api/name', [LabelController::class, 'legacy']);
    }

    public function test_each_variant_document_has_the_operations_and_components_of_its_variant(): void
    {
        self::assertSame([['/api/name'], ['Name']], $this->generated('2026-09-01'));
        self::assertSame([['/api/label'], ['Label']], $this->generated('2026-12-01'));
        self::assertSame([['/api/label'], ['Label']], $this->generated(null));
    }

    public function test_an_operation_out_of_bounds_is_not_read_at_the_variant(): void
    {
        $this->router()->get('api/broken', [LabelController::class, 'broken']);
        $configuration = $this->variantConfiguration();

        $generated = $this->boundsGenerator()->generate($configuration, 'v1', variant: $configuration->document('v1')->generation('2026-09-01'));

        self::assertSame([], array_map(strval(...), $generated->context->diagnostics->all()));
        self::assertSame(['Name'], array_keys($generated->context->components->all()));
    }

    public function test_an_operation_in_bounds_is_read_at_the_variant(): void
    {
        $this->router()->get('api/broken', [LabelController::class, 'broken']);
        $configuration = $this->variantConfiguration();

        $generated = $this->boundsGenerator()->generate($configuration, 'v1', variant: $configuration->document('v1')->generation('2026-12-01'));

        self::assertNotSame([], $generated->context->diagnostics->all());
    }

    public function test_a_document_without_variants_lists_every_operation_and_component(): void
    {
        $written = $this->boundsGenerator()->generate(Configuration::fromArray(['documents' => ['v1' => [
            'info' => ['title' => 'API', 'version' => '1'],
            'routes' => ['include' => null],
        ]]]), 'v1')->openApi->toArray(Version::V3_1_2);

        self::assertSame(['/api/label', '/api/name'], array_keys((array) Schemas::at($written, 'paths')));
        self::assertSame(['Label', 'Name'], array_keys((array) Schemas::at($written, 'components', 'schemas')));
    }

    /**
     * @return array{list<array-key>, list<array-key>} the paths and the component schemas of the document of that variant
     */
    private function generated(?string $variant): array
    {
        $configuration = $this->variantConfiguration();
        $generated = $this->boundsGenerator()->generate($configuration, 'v1', variant: $variant === null ? null : $configuration->document('v1')->generation($variant));
        $written = $generated->openApi->toArray(Version::V3_1_2);

        return [array_keys((array) Schemas::at($written, 'paths')), array_keys((array) Schemas::at($written, 'components', 'schemas'))];
    }

    private function variantConfiguration(): Configuration
    {
        return Configuration::fromArray(['documents' => ['v1' => [
            'info' => ['title' => 'API', 'version' => '{variant}'],
            'routes' => ['include' => null],
            'variants' => ['values' => ApiRevision::class, 'head' => 'latest'],
        ]]]);
    }

    private function boundsGenerator(): Generator
    {
        $container = $this->app?->make(Container::class);
        self::assertInstanceOf(Container::class, $container);

        return Generator::make($this->router(), $container, $this->samplingSession());
    }
}
