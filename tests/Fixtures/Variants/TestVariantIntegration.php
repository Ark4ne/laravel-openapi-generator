<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Variants;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Extraction\Exceptions\ErrorBodies;
use Ark4ne\OpenApi\Generation\VariantIntegration;
use Illuminate\Http\Request;

/**
 * A variant integration, available or not: it marks the sample requests with its name and the variant, and describes
 * the variant with its own text (`null` for none).
 */
final readonly class TestVariantIntegration implements VariantIntegration
{
    public function __construct(
        private string $name,
        private bool $available = true,
        private ?string $description = null,
    ) {}

    public function available(): bool
    {
        return $this->available;
    }

    public function resourceFormats(): array
    {
        return [];
    }

    public function ruleMappers(): array
    {
        return [];
    }

    public function errorBodies(): ?ErrorBodies
    {
        return null;
    }

    public function prepareSampleRequest(Request $request, GenerationContext $context): void
    {
        $prepared = $request->attributes->get('prepared');
        $request->attributes->set('prepared', (is_string($prepared) ? "{$prepared}," : '') . "{$this->name}:" . ($context->variantName() ?? 'none'));
    }

    public function describe(GenerationContext $context): ?string
    {
        return $this->description === null ? null : "{$this->description} ({$context->variantName()})";
    }
}
