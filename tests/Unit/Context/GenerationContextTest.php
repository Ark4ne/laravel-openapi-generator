<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Context;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Configuration\InfoConfiguration;
use Ark4ne\OpenApi\Configuration\Variant;
use Ark4ne\OpenApi\Configuration\VariantsConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\ApiRevision;
use Ark4ne\OpenApi\Tests\TestCase;
use Closure;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(GenerationContext::class)]
final class GenerationContextTest extends TestCase
{
    public function test_the_context_carries_the_variant_of_the_generation(): void
    {
        $document = new DocumentConfiguration('v1', new InfoConfiguration('API', '1'));
        $variant = Variant::ofCase(ApiRevision::Labels, 1);

        $context = GenerationContext::for(new Configuration(['v1' => $document]), $document, 'fr', $variant);

        self::assertSame($variant, $context->variant);
        self::assertSame('fr', $context->locale);
    }

    public function test_a_context_without_variant_is_the_head(): void
    {
        $document = new DocumentConfiguration('v1', new InfoConfiguration('API', '1'));

        self::assertNull(GenerationContext::for(new Configuration(['v1' => $document]), $document)->variant);
    }

    public function test_the_variant_name_is_that_of_the_variant_or_of_the_head(): void
    {
        $variants = new VariantsConfiguration([Variant::ofCase(ApiRevision::Initial, 0), Variant::ofCase(ApiRevision::Labels, 1)], head: 'latest');
        $document = new DocumentConfiguration('v1', new InfoConfiguration('API', '1'), variants: $variants);
        $configuration = new Configuration(['v1' => $document]);

        self::assertSame('2026-12-01', GenerationContext::for($configuration, $document, variant: Variant::ofCase(ApiRevision::Labels, 1))->variantName());
        self::assertSame('latest', GenerationContext::for($configuration, $document)->variantName());
    }

    public function test_a_document_without_variants_has_no_variant_name(): void
    {
        $document = new DocumentConfiguration('v1', new InfoConfiguration('API', '1'));

        self::assertNull(GenerationContext::for(new Configuration(['v1' => $document]), $document)->variantName());
    }

    public function test_a_sample_request_carries_the_variant_header(): void
    {
        $request = $this->revisions(Variant::ofCase(ApiRevision::Labels, 1))->sampleRequest();

        self::assertSame('GET', $request->getMethod());
        self::assertSame('/', $request->getPathInfo());
        self::assertSame('2026-12-01', $request->header('Api-Version'));
    }

    public function test_a_sample_request_of_the_head_has_no_header(): void
    {
        self::assertFalse($this->revisions(null)->sampleRequest()->headers->has('Api-Version'));
    }

    public function test_a_sample_request_without_configured_header_has_none(): void
    {
        $request = $this->revisions(Variant::ofCase(ApiRevision::Labels, 1), header: null)->sampleRequest();

        self::assertFalse($request->headers->has('Api-Version'));
    }

    public function test_the_preparers_prepare_every_sample_request_in_order(): void
    {
        $context = $this->revisions(Variant::ofCase(ApiRevision::Labels, 1), preparers: [
            static fn(Request $request, GenerationContext $context) => $request->attributes->set('prepared', [$context->variantName()]),
            static fn(Request $request) => $request->attributes->set('prepared', [...(array) $request->attributes->get('prepared'), 'second']),
        ]);

        self::assertSame(['2026-12-01', 'second'], $context->sampleRequest()->attributes->get('prepared'));
    }

    public function test_the_preparers_see_the_variant_header(): void
    {
        $context = $this->revisions(Variant::ofCase(ApiRevision::Labels, 1), preparers: [
            static fn(Request $request) => $request->attributes->set('seen', $request->header('Api-Version')),
        ]);

        self::assertSame('2026-12-01', $context->sampleRequest()->attributes->get('seen'));
    }

    public function test_a_request_of_the_generator_is_prepared_as_a_sample_request(): void
    {
        $context = $this->revisions(Variant::ofCase(ApiRevision::Labels, 1), preparers: [
            static fn(Request $request) => $request->attributes->set('prepared', true),
        ]);
        $request = Request::create('/api/users/1', 'PUT');

        self::assertSame($request, $context->prepareRequest($request));
        self::assertSame('2026-12-01', $request->header('Api-Version'));
        self::assertTrue($request->attributes->get('prepared'));
        self::assertSame('PUT', $request->getMethod());
    }

    public function test_a_failing_preparer_is_reported_and_the_next_ones_run(): void
    {
        $context = $this->revisions(Variant::ofCase(ApiRevision::Labels, 1), preparers: [
            static fn() => throw new RuntimeException('no revision'),
            static fn(Request $request) => $request->attributes->set('prepared', true),
        ]);

        $request = $context->sampleRequest();

        self::assertTrue($request->attributes->get('prepared'));
        self::assertSame('2026-12-01', $request->header('Api-Version'));
        self::assertDiagnostics(['[warning] sampling: cannot prepare a sample request: no revision'], $context);
    }

    public function test_every_sample_request_is_a_new_request(): void
    {
        $context = $this->revisions(null);
        $first = $context->sampleRequest();
        $first->attributes->set('changed', true);

        self::assertNotSame($first, $context->sampleRequest());
        self::assertFalse($context->sampleRequest()->attributes->has('changed'));
    }

    /**
     * @param list<Closure(Request, GenerationContext): void> $preparers
     */
    private function revisions(?Variant $variant, ?string $header = 'Api-Version', array $preparers = []): GenerationContext
    {
        $variants = new VariantsConfiguration([Variant::ofCase(ApiRevision::Initial, 0), Variant::ofCase(ApiRevision::Labels, 1)], head: 'latest', header: $header);
        $document = new DocumentConfiguration('v1', new InfoConfiguration('API', '1'), variants: $variants);

        return GenerationContext::for(new Configuration(['v1' => $document]), $document, variant: $variant, requestPreparers: $preparers);
    }
}
