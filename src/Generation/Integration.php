<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApi\Extraction\Responses\ResourceFormat;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;

/**
 * Documents a third-party package (`Integrations/`): what it adds to the registries of the generator. The generator
 * uses an integration only when it is available, so that an integration whose package is missing loads none of the
 * classes of that package.
 */
interface Integration
{
    /**
     * Whether the package it documents is installed.
     */
    public function available(): bool;

    /**
     * @return list<ResourceFormat>
     */
    public function resourceFormats(): array;

    /**
     * Registered after the built-in mappers: they replace them for their rules.
     *
     * @return list<RuleMapper>
     */
    public function ruleMappers(): array;

    /**
     * The bodies of the error responses the passes add, `null` to keep Laravel's.
     */
    public function errorBodies(): ?ErrorBodies;
}
