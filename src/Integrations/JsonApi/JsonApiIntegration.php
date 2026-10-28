<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApi;

use Ark4ne\OpenApi\Generation\ErrorBodies;
use Ark4ne\OpenApi\Generation\Integration;

/**
 * `ark4ne/laravel-json-api` (^1.9): its resources and collections in `application/vnd.api+json`, the `Includes` and
 * `Fields` rules as the `include` and `fields[type]` parameters and, when the application renders them, JSON:API
 * error bodies (the package itself leaves Laravel's errors as they are).
 */
final readonly class JsonApiIntegration implements Integration
{
    /**
     * The class whose presence tells the package is installed, named as a string so that nothing is loaded.
     */
    public const PACKAGE_CLASS = 'Ark4ne\JsonApi\Resources\JsonApiResource';

    /**
     * @param bool $errors the error responses are JSON:API error documents (`errors[]`)
     */
    public function __construct(
        private bool $errors = false,
    ) {}

    public function available(): bool
    {
        return class_exists(self::PACKAGE_CLASS);
    }

    public function resourceFormats(): array
    {
        return [new JsonApiResourceFormat()];
    }

    public function ruleMappers(): array
    {
        return [new IncludesRuleMapper(), new FieldsRuleMapper()];
    }

    public function errorBodies(): ?ErrorBodies
    {
        return $this->errors ? new JsonApiErrorBodies() : null;
    }
}
