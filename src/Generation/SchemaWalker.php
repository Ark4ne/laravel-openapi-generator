<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApi\Description\BodyDescription;
use Ark4ne\OpenApi\Description\CallbackDescription;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\HeaderDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApiWriter\Contracts\SchemaContract;
use Ark4ne\OpenApiWriter\Objects\Reference;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Closure;

/**
 * Applies a function to every schema of a document, children first, or visits them without rebuilding anything. The
 * children are those of every keyword that holds schemas: the keyed ones (`properties`, `patternProperties`,
 * `dependentSchemas`, `$defs`), the single ones (`additionalProperties`, `propertyNames`, `unevaluatedProperties`,
 * `items`, `contains`, `unevaluatedItems`, `not`, `if`, `then`, `else`, `contentSchema`) and the lists
 * (`prefixItems`, `allOf`, `anyOf`, `oneOf`).
 */
final class SchemaWalker
{
    private const string COMPONENTS = '#/components/schemas/';

    /**
     * The name of the component schema `$schema` references, `null` for any other schema.
     */
    public static function component(SchemaContract $schema): ?string
    {
        $ref = $schema instanceof Schema || $schema instanceof Reference ? $schema->ref : null;

        return is_string($ref) && str_starts_with($ref, self::COMPONENTS) ? substr($ref, strlen(self::COMPONENTS)) : null;
    }

    /**
     * @param Closure(Schema): Schema $map
     */
    public static function schema(Schema $schema, Closure $map): Schema
    {
        $child = static fn(SchemaContract $child): SchemaContract => $child instanceof Schema ? self::schema($child, $map) : $child;
        $single = static fn(SchemaContract|bool|null $child): SchemaContract|bool|null => $child instanceof Schema ? self::schema($child, $map) : $child;

        $schema = $schema->properties === null || $schema->properties === [] ? $schema : $schema->properties(...self::keyed($schema->properties, $map));
        $schema = $schema->patternProperties === null || $schema->patternProperties === [] ? $schema : $schema->patternProperties(...self::keyed($schema->patternProperties, $map));
        $schema = $schema->dependentSchemas === null || $schema->dependentSchemas === [] ? $schema : $schema->dependentSchemas(...self::keyed($schema->dependentSchemas, $map));
        $schema = $schema->defs === null || $schema->defs === [] ? $schema : $schema->defs(...self::keyed($schema->defs, $map));
        $schema = $schema->additionalProperties instanceof Schema ? $schema->additionalProperties($single($schema->additionalProperties)) : $schema;
        $schema = $schema->propertyNames instanceof Schema ? $schema->propertyNames($single($schema->propertyNames)) : $schema;
        $schema = $schema->unevaluatedProperties instanceof Schema ? $schema->unevaluatedProperties($single($schema->unevaluatedProperties)) : $schema;
        $schema = $schema->items instanceof Schema ? $schema->items($single($schema->items)) : $schema;
        $schema = $schema->prefixItems === null || $schema->prefixItems === [] ? $schema : $schema->prefixItems(...array_map($child, $schema->prefixItems));
        $schema = $schema->contains instanceof Schema ? $schema->contains($single($schema->contains)) : $schema;
        $schema = $schema->unevaluatedItems instanceof Schema ? $schema->unevaluatedItems($single($schema->unevaluatedItems)) : $schema;
        $schema = $schema->allOf === null || $schema->allOf === [] ? $schema : $schema->allOf(...array_map($child, $schema->allOf));
        $schema = $schema->anyOf === null || $schema->anyOf === [] ? $schema : $schema->anyOf(...array_map($child, $schema->anyOf));
        $schema = $schema->oneOf === null || $schema->oneOf === [] ? $schema : $schema->oneOf(...array_map($child, $schema->oneOf));
        $schema = $schema->not instanceof Schema ? $schema->not($single($schema->not)) : $schema;
        $schema = $schema->if instanceof Schema ? $schema->if($single($schema->if)) : $schema;
        $schema = $schema->then instanceof Schema ? $schema->then($single($schema->then)) : $schema;
        $schema = $schema->else instanceof Schema ? $schema->else($single($schema->else)) : $schema;
        $schema = $schema->contentSchema instanceof Schema ? $schema->contentSchema(self::schema($schema->contentSchema, $map)) : $schema;

        return $map($schema);
    }

    /**
     * The children of a keyed keyword, mapped, each keeping its key as its objectId.
     *
     * @param array<string, SchemaContract> $children
     * @param Closure(Schema): Schema $map
     *
     * @return list<SchemaContract>
     */
    private static function keyed(array $children, Closure $map): array
    {
        $mapped = [];

        foreach ($children as $key => $child) {
            $mapped[] = $child instanceof Schema ? self::schema($child, $map)->objectId($key) : $child;
        }

        return $mapped;
    }

    /**
     * Calls `$visit` on every schema and schema reference of `$schema`, children first, itself last, without
     * rebuilding anything.
     *
     * @param Closure(SchemaContract): void $visit
     */
    public static function visitSchema(SchemaContract $schema, Closure $visit): void
    {
        if ($schema instanceof Schema) {
            $children = [
                ...array_values($schema->properties ?? []),
                ...array_values($schema->patternProperties ?? []),
                ...array_values($schema->dependentSchemas ?? []),
                ...array_values($schema->defs ?? []),
                $schema->additionalProperties,
                $schema->propertyNames,
                $schema->unevaluatedProperties,
                $schema->items,
                ...$schema->prefixItems ?? [],
                $schema->contains,
                $schema->unevaluatedItems,
                ...$schema->allOf ?? [],
                ...$schema->anyOf ?? [],
                ...$schema->oneOf ?? [],
                $schema->not,
                $schema->if,
                $schema->then,
                $schema->else,
                $schema->contentSchema,
            ];

            foreach ($children as $child) {
                if ($child instanceof SchemaContract) {
                    self::visitSchema($child, $visit);
                }
            }
        }

        $visit($schema);
    }

    /**
     * Calls `$visit` on every schema and schema reference of a document (operations, webhooks and their callbacks,
     * components, shared responses, path parameters), without rebuilding anything.
     *
     * @param Closure(SchemaContract): void $visit
     */
    public static function visit(DocumentDescription $document, Closure $visit): void
    {
        foreach ([...$document->operations, ...$document->webhooks] as $operation) {
            self::visitOperation($operation, $visit);
        }

        foreach ($document->schemas as $schema) {
            self::visitSchema($schema, $visit);
        }

        foreach ($document->responses as $response) {
            self::visitResponse($response, $visit);
        }

        foreach ($document->pathParameters as $parameters) {
            foreach ($parameters as $parameter) {
                self::visitSchema($parameter->schema, $visit);
            }
        }
    }

    /**
     * @param Closure(Schema): Schema $map
     */
    public static function document(DocumentDescription $document, Closure $map): DocumentDescription
    {
        $schemas = [];

        foreach ($document->schemas as $name => $schema) {
            $schemas[$name] = self::schema($schema, $map);
        }

        $responses = [];

        foreach ($document->responses as $name => $response) {
            $responses[$name] = self::response($response, $map);
        }

        $document = $document
            ->operations(...array_map(static fn(OperationDescription $operation): OperationDescription => self::operation($operation, $map), $document->operations))
            ->webhooks(...array_map(static fn(OperationDescription $operation): OperationDescription => self::operation($operation, $map), $document->webhooks))
            ->schemas($schemas)
            ->responses($responses);

        foreach ($document->pathParameters as $path => $parameters) {
            $document = $document->pathParameters($path, ...self::parameters($parameters, $map));
        }

        return $document;
    }

    /**
     * @param Closure(Schema): Schema $map
     */
    private static function operation(OperationDescription $operation, Closure $map): OperationDescription
    {
        $request = $operation->request->parameters(...self::parameters($operation->request->parameters, $map));
        $body = $request->body;

        if ($body instanceof BodyDescription) {
            $request = $request->body($body->contents(array_map(static fn(Schema $schema): Schema => self::schema($schema, $map), $body->contents)));
        }

        return $operation
            ->request($request)
            ->responses(...array_map(static fn(ResponseDescription $response): ResponseDescription => self::response($response, $map), $operation->responses))
            ->callbacks(...array_map(static fn(CallbackDescription $callback): CallbackDescription => $callback->operation === null ? $callback : $callback->operation(self::operation($callback->operation, $map)), $operation->callbacks));
    }

    /**
     * @param list<ParameterDescription> $parameters
     * @param Closure(Schema): Schema $map
     *
     * @return list<ParameterDescription>
     */
    private static function parameters(array $parameters, Closure $map): array
    {
        return array_map(static fn(ParameterDescription $parameter): ParameterDescription => $parameter->schema(self::schema($parameter->schema, $map)), $parameters);
    }

    /**
     * @param Closure(Schema): Schema $map
     */
    private static function response(ResponseDescription $response, Closure $map): ResponseDescription
    {
        return $response
            ->contents(array_map(static fn(Schema $schema): Schema => self::schema($schema, $map), $response->contents))
            ->itemContents(array_map(static fn(Schema $schema): Schema => self::schema($schema, $map), $response->itemContents))
            ->headers(...array_map(static fn(HeaderDescription $header): HeaderDescription => $header->schema(self::schema($header->schema, $map)), $response->headers));
    }

    /**
     * @param Closure(SchemaContract): void $visit
     */
    private static function visitOperation(OperationDescription $operation, Closure $visit): void
    {
        foreach ($operation->request->parameters as $parameter) {
            self::visitSchema($parameter->schema, $visit);
        }

        $body = $operation->request->body;

        foreach ($body instanceof BodyDescription ? $body->contents : [] as $schema) {
            self::visitSchema($schema, $visit);
        }

        foreach ($operation->responses as $response) {
            self::visitResponse($response, $visit);
        }

        foreach ($operation->callbacks as $callback) {
            if ($callback->operation !== null) {
                self::visitOperation($callback->operation, $visit);
            }
        }
    }

    /**
     * @param Closure(SchemaContract): void $visit
     */
    private static function visitResponse(ResponseDescription $response, Closure $visit): void
    {
        foreach ([...array_values($response->contents), ...array_values($response->itemContents)] as $schema) {
            self::visitSchema($schema, $visit);
        }

        foreach ($response->headers as $header) {
            self::visitSchema($header->schema, $visit);
        }
    }
}
