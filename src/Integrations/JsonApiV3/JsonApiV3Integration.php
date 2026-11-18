<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApiV3;

use Ark4ne\ApiRevisions\Revisions\RevisionScope;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Extraction\Exceptions\ErrorBodies;
use Ark4ne\OpenApi\Generation\DocumentIntegration;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;

/**
 * `ark4ne/laravel-json-api` (^3.0, on `ark4ne/laravel-api-revisions`), read through the definitions it compiles: its
 * resources and collections in `application/vnd.api+json`, their values typed by the shapes of the base, the
 * `Http\Rules\Includes` and `Http\Rules\Fields` rules as the `include` and `fields[type]` parameters and, when the
 * application renders them (`JsonApi::renderErrors()`), its error documents.
 *
 * A document documents one revision of the API (`documents.<name>.integrations.json_api.revision`, read by
 * `Revisions`): its definitions, and every request the generator builds is read at that revision (the base revision of
 * the `RevisionScope` of the base: the samples render, and the form requests read their rules, as a client of that
 * revision); its description lists the newer revisions. Without the setting, the head.
 */
final readonly class JsonApiV3Integration implements DocumentIntegration
{
    /**
     * The class whose presence tells the package is installed, named as a string so that nothing is loaded.
     */
    public const PACKAGE_CLASS = 'Ark4ne\JsonApi\Resources\JsonApiResource';

    /**
     * A class of 2.x that 3.0 removed: the values of its definitions, replaced by the shapes of the base.
     */
    public const V2_CLASS = 'Ark4ne\JsonApi\Definition\AttributeDefinition';

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
     * @param Container|null $container the application's, for the check of its resources (`ResourceCheckPass`)
     */
    public function __construct(
        private bool $errors = false,
        private ?Container $container = null,
    ) {
        $this->revisions = new Revisions();
        $this->definitions = new ResourceDefinitions($this->revisions);
    }

    /**
     * Installed in 3.x: its resources expose their definition (`definition()`), and the values of 2.x are gone.
     */
    public function available(): bool
    {
        return class_exists(self::PACKAGE_CLASS) && method_exists(self::PACKAGE_CLASS, 'definition') && ! class_exists(self::V2_CLASS);
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
     * The revision of the document as the base revision of the scope of the base (`RevisionScope::setBase()`), the head
     * (`null`) included, so that a document never inherits the revision of the previous one.
     */
    public function prepareRequest(Request $request, GenerationContext $context): void
    {
        RevisionScope::get()->setBase($this->revisions->of($context));
    }

    /**
     * The rules unchanged: those of a request are read at the revision its preparation set.
     */
    public function prepareRules(array $rules, Request $request, GenerationContext $context): array
    {
        return $rules;
    }

    /**
     * The changelog of the revisions newer than that of the document (`Revisions::changelog()`).
     */
    public function describe(GenerationContext $context): ?string
    {
        return Revisions::changelog($this->revisions->of($context));
    }

    /**
     * The check of the resources the document covers (`ResourceCheckPass`).
     */
    public function passes(GenerationContext $context): array
    {
        return [new ResourceCheckPass($this->definitions, $this->revisions, $this->container)];
    }
}
