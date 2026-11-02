<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApiV2;

use Ark4ne\OpenApi\Extraction\Exceptions\ErrorBodies;
use Ark4ne\OpenApi\Generation\Integration;

/**
 * `ark4ne/laravel-json-api` (^2.0), read through the definitions it compiles: its resources and collections in
 * `application/vnd.api+json`, the `Http\Rules\Includes` and `Http\Rules\Fields` rules as the `include` and
 * `fields[type]` parameters and, when the application renders them (`JsonApi::renderErrors()`), its error documents.
 */
final readonly class JsonApiV2Integration implements Integration
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

    /**
     * Installed in 2.x: its resources expose their definition (`definition()`), which 1.x does not.
     */
    public function available(): bool
    {
        return class_exists(self::PACKAGE_CLASS) && method_exists(self::PACKAGE_CLASS, 'definition');
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
