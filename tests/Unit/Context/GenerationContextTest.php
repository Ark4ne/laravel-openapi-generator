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
use PHPUnit\Framework\Attributes\CoversClass;

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
}
