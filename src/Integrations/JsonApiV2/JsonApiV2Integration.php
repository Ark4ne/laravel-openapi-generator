<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApiV2;

use Ark4ne\JsonApi\Http\Rules\Revised;
use Ark4ne\JsonApi\Laravel\JsonApi;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Extraction\Exceptions\ErrorBodies;
use Ark4ne\OpenApi\Generation\DocumentIntegration;
use Illuminate\Http\Request;

/**
 * `ark4ne/laravel-json-api` (^2.0), read through the definitions it compiles: its resources and collections in
 * `application/vnd.api+json`, the `Http\Rules\Includes` and `Http\Rules\Fields` rules as the `include` and
 * `fields[type]` parameters and, when the application renders them (`JsonApi::renderErrors()`), its error documents.
 *
 * With 2.1, a document documents one revision of the API (`documents.<name>.integrations.json_api.revision`, read by
 * `Revisions`): its definitions, the samples and the rules of its form requests (`Http\Rules\Revised`) are those of
 * that revision, and its description lists the newer revisions. Without the setting, or with 2.0, the head.
 */
final readonly class JsonApiV2Integration implements DocumentIntegration
{
    /**
     * The class whose presence tells the package is installed, named as a string so that nothing is loaded.
     */
    public const PACKAGE_CLASS = 'Ark4ne\JsonApi\Resources\JsonApiResource';

    /**
     * The definitions its format and its rule mappers read: each one once per context, its findings relayed once.
     */
    private ResourceDefinitions $definitions;

    /**
     * The revision of each document, shared with the definitions.
     */
    private Revisions $revisions;

    /**
     * @param bool $errors the error responses are JSON:API error documents (`errors[]`)
     */
    public function __construct(
        private bool $errors = false,
    ) {
        $this->revisions = new Revisions();
        $this->definitions = new ResourceDefinitions($this->revisions);
    }

    /**
     * A class of 2.x that 3.0 removed: its values, replaced in 3.0 by the shapes of `ark4ne/laravel-api-revisions`.
     */
    public const V2_CLASS = 'Ark4ne\JsonApi\Definition\AttributeDefinition';

    /**
     * Installed in 2.x: its resources expose their definition (`definition()`), which 1.x does not, and their values
     * are its own (`AttributeDefinition`), which 3.0 does not have.
     */
    public function available(): bool
    {
        return class_exists(self::PACKAGE_CLASS) && method_exists(self::PACKAGE_CLASS, 'definition') && class_exists(self::V2_CLASS);
    }

    public function resourceFormats(): array
    {
        return [new JsonApiResourceFormat($this->definitions)];
    }

    public function ruleMappers(): array
    {
        return [new IncludesRuleMapper($this->definitions), new FieldsRuleMapper($this->definitions)];
    }

    public function errorBodies(): ?ErrorBodies
    {
        return $this->errors ? new JsonApiErrorBodies() : null;
    }

    public function key(): string
    {
        return Revisions::KEY;
    }

    /**
     * The revision of the document on every request the generator builds (`JsonApi::useRevision()`): the resources
     * render, and the form requests read their rules, as a client of that revision. Nothing for the head.
     */
    public function prepareRequest(Request $request, GenerationContext $context): void
    {
        $revision = $this->revisions->of($context);

        if ($revision !== null) {
            JsonApi::useRevision($request, $revision);
        }
    }

    /**
     * The `Http\Rules\Revised` values of the rules resolved at the revision of the document (`Revised::resolve()`, the
     * head without revision): a `JsonApiFormRequest`, a request using `UseRevisedRules` and a plain request are read
     * alike. The rules unchanged with 2.0.
     */
    public function prepareRules(array $rules, Request $request, GenerationContext $context): array
    {
        return Revisions::supported() ? Revised::resolve($rules, $this->revisions->of($context)) : $rules;
    }

    /**
     * The changelog of the revisions newer than that of the document (`Revisions::changelog()`).
     */
    public function describe(GenerationContext $context): ?string
    {
        return Revisions::changelog($this->revisions->of($context));
    }

    public function passes(GenerationContext $context): array
    {
        return [];
    }
}
