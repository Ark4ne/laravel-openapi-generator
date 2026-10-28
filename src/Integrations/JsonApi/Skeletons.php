<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApi;

use Ark4ne\JsonApi\Resources\JsonApiResource;
use Ark4ne\JsonApi\Resources\Skeleton;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use ReflectionProperty;
use Throwable;

/**
 * The skeleton the package builds for a resource (`JsonApiResource::schema()`): its type, its fields and its
 * relationships, as its `Includes` and `Fields` rules validate them.
 */
final class Skeletons
{
    /**
     * The skeleton of the resource of a rule, `null` with a warning when the package cannot build it.
     *
     * @return Skeleton<mixed>|null
     */
    public static function ofRule(object $rule, GenerationContext $context, string $field): ?Skeleton
    {
        $resource = (new ReflectionProperty($rule, 'resource'))->getValue($rule);

        if (! is_string($resource) || ! is_subclass_of($resource, JsonApiResource::class)) {
            return null;
        }

        try {
            return $resource::schema();
        } catch (Throwable $error) {
            $context->diagnostics->warning(Stage::Request, "{$field}: cannot read the schema of {$resource}: {$error->getMessage()}");

            return null;
        }
    }
}
