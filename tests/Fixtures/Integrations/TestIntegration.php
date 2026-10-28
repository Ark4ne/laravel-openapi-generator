<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Integrations;

use Ark4ne\OpenApi\Generation\ErrorBodies;
use Ark4ne\OpenApi\Generation\Integration;

/**
 * An integration available or not, with a format of every resource and error bodies of its own media types.
 */
final readonly class TestIntegration implements Integration
{
    public function __construct(
        private bool $available,
        private string $formatMediaType,
        private string $errorsMediaType,
    ) {}

    public function available(): bool
    {
        return $this->available;
    }

    public function resourceFormats(): array
    {
        return [new TestResourceFormat($this->formatMediaType)];
    }

    public function ruleMappers(): array
    {
        return [];
    }

    public function errorBodies(): ErrorBodies
    {
        return new TestErrorBodies($this->errorsMediaType);
    }
}
