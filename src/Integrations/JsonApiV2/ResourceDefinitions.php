<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApiV2;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\JsonApiObject;
use Ark4ne\JsonApi\Definition\RelatedResource;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Definition\Revision;
use Ark4ne\JsonApi\Support\DiagnosticLevel;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Throwable;
use WeakMap;

/**
 * The definitions the package compiles (`Definitions::for()`), its public contract for the tools: read once per
 * context and revision, the findings of their compilation relayed as diagnostics, a definition the package cannot
 * compile left out with a warning. A resource absent from the revision (`Definitions::exists()`) is left out with a
 * warning naming it, so that the application excludes its routes from the document (`routes.exclude`).
 */
final class ResourceDefinitions
{
    /** @var WeakMap<GenerationContext, array<string, ResourceDefinition|null>> class (`Class@revision` at a revision) → definition, per context */
    private WeakMap $definitions;

    /** @var WeakMap<GenerationContext, array<string, true>> the findings already relayed, per context */
    private WeakMap $relayed;

    public function __construct(
        private readonly Revisions $revisions = new Revisions(),
    ) {
        $this->definitions = new WeakMap();
        $this->relayed = new WeakMap();
    }

    /**
     * The definition of a resource, or of the resource a collection collects, at a revision; `null` with a warning when
     * the package cannot compile it or when it does not exist at that revision.
     *
     * @param string $subject what the definition is read for, named in the warning
     * @param Revision|false|null $revision the revision to read it at: `false` (the default) for that of the document,
     *        `null` for the head (what a rule built without revision validates)
     */
    public function of(string $class, GenerationContext $context, Stage $stage, string $subject, Revision|false|null $revision = false): ?ResourceDefinition
    {
        $revision = $revision === false ? $this->revisions->of($context) : $revision;
        $key = $revision === null ? $class : $class . '@' . Revisions::name($revision);
        $known = $this->definitions[$context] ?? [];

        if (array_key_exists($key, $known)) {
            return $known[$key];
        }

        try {
            $definition = $this->definition(RelatedResource::resolve($class), $revision, $context, $stage);
        } catch (Throwable $error) {
            $context->diagnostics->warning($stage, "{$subject}: cannot read the definition of {$class}: {$error->getMessage()}");
            $definition = null;
        }

        // An error of the package is a warning here: the document is still written, an error being kept for a document
        // that is not valid (`--strict` fails on warnings). Read once per revision, each finding is relayed once.
        $relayed = $this->relayed[$context] ?? [];

        foreach ($definition->diagnostics ?? [] as $diagnostic) {
            $message = "{$class}: {$diagnostic->message} ({$diagnostic->code})";

            if (isset($relayed[$message])) {
                continue;
            }

            $relayed[$message] = true;

            match ($diagnostic->level) {
                DiagnosticLevel::Info => $context->diagnostics->info($stage, $message),
                DiagnosticLevel::Warning, DiagnosticLevel::Error => $context->diagnostics->warning($stage, $message),
            };
        }

        $this->relayed[$context] = $relayed;
        $known = $this->definitions[$context] ?? [];
        $known[$key] = $definition;
        $this->definitions[$context] = $known;

        return $definition;
    }

    /**
     * The definition at a revision, `null` with a warning when the resource does not exist at it: the application
     * excludes its routes from that document.
     *
     * @param class-string<\Ark4ne\JsonApi\Definition\DefinitionSource> $resource
     */
    private function definition(string $resource, ?Revision $revision, GenerationContext $context, Stage $stage): ?ResourceDefinition
    {
        if ($revision === null) {
            return Definitions::for($resource);
        }

        if (! Definitions::exists($resource, $revision)) {
            $context->diagnostics->warning($stage, "{$resource} does not exist at the revision " . Revisions::name($revision) . ': exclude its routes from the document (routes.exclude)');

            return null;
        }

        return Definitions::for($resource, $revision);
    }

    /**
     * The type of a resource of Laravel's native JSON:API, read without model; `null` when its `toType()` needs one.
     *
     * @param class-string<\Illuminate\Http\Resources\JsonApi\JsonApiResource> $native
     */
    public static function nativeType(string $native): ?string
    {
        try {
            return (new $native(null))->resolveResourceType(new JsonApiRequest());
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The top-level `jsonapi` member of the documents, as configured when the application booted; `null` for none.
     */
    public static function jsonApiObject(): ?JsonApiObject
    {
        return Definitions::configuration()->jsonapi;
    }
}
