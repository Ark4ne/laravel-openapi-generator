<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApi\Description\BodyDescription;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\HeaderDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApiWriter\Contracts\SchemaContract;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Closure;

/**
 * Applies a function to every schema of a document, children first (properties, items, compositions…).
 */
final class SchemaWalker
{
    /**
     * @param Closure(Schema): Schema $map
     */
    public static function schema(Schema $schema, Closure $map): Schema
    {
        $properties = [];

        foreach ($schema->properties ?? [] as $name => $property) {
            $properties[] = $property instanceof Schema ? self::schema($property, $map)->objectId($name) : $property;
        }

        if ($properties !== []) {
            $schema = $schema->properties(...$properties);
        }

        $child = static fn(SchemaContract $child): SchemaContract => $child instanceof Schema ? self::schema($child, $map) : $child;

        if ($schema->items instanceof Schema) {
            $schema = $schema->items(self::schema($schema->items, $map));
        }

        if ($schema->not instanceof Schema) {
            $schema = $schema->not(self::schema($schema->not, $map));
        }

        if ($schema->additionalProperties instanceof Schema) {
            $schema = $schema->additionalProperties(self::schema($schema->additionalProperties, $map));
        }

        if ($schema->contains instanceof Schema) {
            $schema = $schema->contains(self::schema($schema->contains, $map));
        }

        $schema = $schema->allOf === null || $schema->allOf === [] ? $schema : $schema->allOf(...array_map($child, $schema->allOf));
        $schema = $schema->anyOf === null || $schema->anyOf === [] ? $schema : $schema->anyOf(...array_map($child, $schema->anyOf));
        $schema = $schema->oneOf === null || $schema->oneOf === [] ? $schema : $schema->oneOf(...array_map($child, $schema->oneOf));
        $schema = $schema->prefixItems === null || $schema->prefixItems === [] ? $schema : $schema->prefixItems(...array_map($child, $schema->prefixItems));

        return $map($schema);
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

        return $document
            ->operations(...array_map(static fn(OperationDescription $operation): OperationDescription => self::operation($operation, $map), $document->operations))
            ->webhooks(...array_map(static fn(OperationDescription $operation): OperationDescription => self::operation($operation, $map), $document->webhooks))
            ->schemas($schemas)
            ->responses($responses);
    }

    /**
     * @param Closure(Schema): Schema $map
     */
    private static function operation(OperationDescription $operation, Closure $map): OperationDescription
    {
        $request = $operation->request->parameters(...array_map(static fn(ParameterDescription $parameter): ParameterDescription => $parameter->schema(self::schema($parameter->schema, $map)), $operation->request->parameters));
        $body = $request->body;

        if ($body instanceof BodyDescription) {
            $request = $request->body($body->contents(array_map(static fn(Schema $schema): Schema => self::schema($schema, $map), $body->contents)));
        }

        return $operation
            ->request($request)
            ->responses(...array_map(static fn(ResponseDescription $response): ResponseDescription => self::response($response, $map), $operation->responses));
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
}
