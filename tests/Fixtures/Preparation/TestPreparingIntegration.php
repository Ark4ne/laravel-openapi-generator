<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Preparation;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Extraction\Exceptions\ErrorBodies;
use Ark4ne\OpenApi\Generation\VariantIntegration;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * An integration that prepares requests, available or not: it marks the requests with its name, and describes the
 * document with its own text (`null` for none). A failing one throws from both; `$onDescribe` runs when it
 * describes.
 */
final readonly class TestPreparingIntegration implements VariantIntegration
{
    public function __construct(
        private string $name,
        private bool $available = true,
        private ?string $description = null,
        private bool $failing = false,
        private ?Closure $onDescribe = null,
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
        if ($this->failing) {
            throw new RuntimeException("{$this->name} cannot prepare");
        }

        $prepared = $request->attributes->get('prepared');
        $request->attributes->set('prepared', (is_string($prepared) ? "{$prepared}," : '') . $this->name);
    }

    public function describe(GenerationContext $context): ?string
    {
        if ($this->onDescribe !== null) {
            ($this->onDescribe)();
        }

        if ($this->failing) {
            throw new RuntimeException("{$this->name} cannot describe");
        }

        return $this->description;
    }
}
