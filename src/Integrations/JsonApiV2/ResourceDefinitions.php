<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApiV2;

use Ark4ne\JsonApi\Compilation\Definitions;
use Ark4ne\JsonApi\Configuration\JsonApiObject;
use Ark4ne\JsonApi\Definition\RelatedResource;
use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Support\DiagnosticLevel;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Illuminate\Http\Resources\JsonApi\JsonApiRequest;
use Throwable;
use WeakMap;

/**
 * The definitions the package compiles (`Definitions::for()`), its public contract for the tools: read once per
 * context, the findings of their compilation relayed as diagnostics, a definition the package cannot compile left out
 * with a warning.
 */
final class ResourceDefinitions
{
    /** @var WeakMap<GenerationContext, array<string, ResourceDefinition|null>> class → definition, per context */
    private WeakMap $definitions;

    public function __construct()
    {
        $this->definitions = new WeakMap();
    }

    /**
     * The definition of a resource, or of the resource a collection collects; `null` with a warning when the package
     * cannot compile it.
     *
     * @param string $subject what the definition is read for, named in the warning
     */
    public function of(string $class, GenerationContext $context, Stage $stage, string $subject): ?ResourceDefinition
    {
        $known = $this->definitions[$context] ?? [];

        if (array_key_exists($class, $known)) {
            return $known[$class];
        }

        try {
            $definition = Definitions::for(RelatedResource::resolve($class));
        } catch (Throwable $error) {
            $context->diagnostics->warning($stage, "{$subject}: cannot read the definition of {$class}: {$error->getMessage()}");
            $definition = null;
        }

        foreach ($definition->diagnostics ?? [] as $diagnostic) {
            $message = "{$class}: {$diagnostic->message} ({$diagnostic->code})";

            match ($diagnostic->level) {
                DiagnosticLevel::Info => $context->diagnostics->info($stage, $message),
                DiagnosticLevel::Warning, DiagnosticLevel::Error => $context->diagnostics->warning($stage, $message),
            };
        }

        $known = $this->definitions[$context] ?? [];
        $known[$class] = $definition;
        $this->definitions[$context] = $known;

        return $definition;
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
