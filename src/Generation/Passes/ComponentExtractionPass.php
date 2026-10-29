<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Passes;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Generation\Pass;
use Ark4ne\OpenApi\Generation\SchemaWalker;
use Ark4ne\OpenApi\Sampling\ComponentNames;
use Ark4ne\OpenApiWriter\Enums\ParameterLocation;
use Ark4ne\OpenApiWriter\Objects\Schema;
use BackedEnum;

/**
 * The components of the generation (resources, errors) and the enums bound by the paths in `components.schemas`;
 * identical schemas kept once, their references following; without `use_references`, every reference written inline.
 */
final readonly class ComponentExtractionPass implements Pass
{
    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        $document = $this->enums($document, $context);
        $schemas = $document->schemas;

        foreach ($context->components->all() as $name => $schema) {
            $schemas[$name] ??= $schema;
        }

        // Identical schemas: the first name stays, the references to the others follow it.
        $kept = [];
        $renamed = [];

        foreach ($schemas as $name => $schema) {
            $json = $schema->objectId(null)->toJson();

            if (isset($kept[$json])) {
                $renamed[$name] = $kept[$json];
                unset($schemas[$name]);
            } else {
                $kept[$json] = $name;
            }
        }

        $document = $document->schemas($schemas);

        if ($renamed !== []) {
            $document = SchemaWalker::document($document, static function (Schema $schema) use ($renamed): Schema {
                $name = self::name($schema);

                return $name !== null && isset($renamed[$name]) ? Schema::ref($renamed[$name]) : $schema;
            });
        }

        return $context->document->useReferences ? $document : $this->inline($document);
    }

    /**
     * Path parameters bound to a backed enum: the enum as a component.
     */
    private function enums(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        if (! $context->document->useReferences) {
            return $document;
        }

        $operations = [];

        foreach ($document->operations as $operation) {
            $parameters = [];

            foreach ($operation->request->parameters as $parameter) {
                $enum = $parameter->boundTo;

                if ($parameter->in === ParameterLocation::Path && $enum !== null && is_subclass_of($enum, BackedEnum::class)) {
                    $name = $context->components->nameOf($enum) ?? $context->components->register($enum, ComponentNames::of($enum), $parameter->schema);
                    $parameter = $parameter->schema(Schema::ref($name));
                }

                $parameters[] = $parameter;
            }

            $operations[] = $operation->request($operation->request->parameters(...$parameters));
        }

        return $document->operations(...$operations);
    }

    /**
     * Every reference to a component replaced by the component itself, components first.
     */
    private function inline(DocumentDescription $document): DocumentDescription
    {
        $schemas = $document->schemas;
        $resolve = static function (Schema $schema) use (&$resolve, &$schemas): Schema {
            $name = self::name($schema);

            return $name !== null && isset($schemas[$name]) ? SchemaWalker::schema($schemas[$name]->objectId(null), $resolve) : $schema;
        };

        return SchemaWalker::document($document->schemas([]), $resolve);
    }

    /**
     * The component a schema references, `null` for any other schema.
     */
    private static function name(Schema $schema): ?string
    {
        $ref = $schema->ref;

        return is_string($ref) && str_starts_with($ref, '#/components/schemas/') ? substr($ref, 21) : null;
    }
}
