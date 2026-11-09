<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Preparation;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Extraction\Exceptions\ErrorBodies;
use Ark4ne\OpenApi\Generation\DocumentIntegration;
use Ark4ne\OpenApi\Generation\Pass;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A document integration, available or not, keyed by its name: it marks the requests with its name, describes the
 * document with its own text (`null` for none) or the `note` of its settings, and adds its passes. A failing one
 * throws from every method; `$onDescribe` runs when it describes.
 */
final readonly class TestDocumentIntegration implements DocumentIntegration
{
    /**
     * @param list<mixed> $passes passes, or anything else for an integration that gives something else
     */
    public function __construct(
        private string $name,
        private bool $available = true,
        private ?string $description = null,
        private bool $failing = false,
        private ?Closure $onDescribe = null,
        private array $passes = [],
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

    public function key(): string
    {
        return $this->name;
    }

    public function prepareRequest(Request $request, GenerationContext $context): void
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

        $note = $context->document->integration($this->name)['note'] ?? null;

        return $this->description ?? (is_string($note) ? $note : null);
    }

    public function passes(GenerationContext $context): array
    {
        if ($this->failing) {
            throw new RuntimeException("{$this->name} cannot give its passes");
        }

        /** @var list<Pass> what it declares, whatever it gives */
        return $this->passes;
    }
}
