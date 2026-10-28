<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Requests;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\BodyDescription;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Description\RequestDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Rules\FieldTree;
use Ark4ne\OpenApi\Extraction\Strategies\QueryStrategy;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Ark4ne\OpenApiWriter\Enums\ParameterLocation;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * Places validated fields: in the query for the methods without body (`GET`, `HEAD`, `DELETE`), else in the body.
 *
 * The body is `application/json`, or `multipart/form-data` as soon as a field is a file; the content types of the
 * files give the encodings of the multipart body, by top-level property (`photos.*` → `photos`).
 *
 * Fields placed again (a manual description after the rules) complete the schema already there: their properties
 * are added, replacing those of the same name, and their required keys too.
 */
final readonly class FieldPlacer
{
    public const JSON = 'application/json';

    public const MULTIPART = 'multipart/form-data';

    private const BODILESS = [HttpMethod::Get, HttpMethod::Head, HttpMethod::Delete];

    public function __construct(
        private QueryStrategy $query,
    ) {}

    /**
     * @param list<FieldDescription> $fields
     * @param bool|null $inQuery `null`: from the method
     * @param string|null $mediaType `null`: JSON, or multipart with a file
     */
    public function place(RequestDescription $request, array $fields, OperationContext $operation, GenerationContext $context, ?bool $inQuery = null, ?string $mediaType = null): RequestDescription
    {
        if ($fields === []) {
            return $request;
        }

        if ($inQuery ?? in_array($operation->method, self::BODILESS, true)) {
            return $request->parameters(...self::merge($request->parameters, $this->query->parameters($fields, $context)));
        }

        $files = array_filter($fields, static fn(FieldDescription $field): bool => $field->file && ! $field->forbidden);
        $mediaType ??= $files === [] ? self::JSON : self::MULTIPART;
        $required = array_filter($fields, static fn(FieldDescription $field): bool => $field->required && ! $field->forbidden);
        $body = $request->body ?? BodyDescription::create();
        $existing = $body->contents[$mediaType] ?? null;
        $schema = FieldTree::object($fields);
        $body = $body
            ->content($mediaType, $existing === null ? $schema : self::combine($existing, $schema))
            ->required($body->required || $required !== []);

        if ($mediaType === self::MULTIPART) {
            foreach (self::encodings($files) as $property => $types) {
                $body = $body->encoding($mediaType, $property, implode(', ', $types));
            }
        }

        return $request->body($body);
    }

    /**
     * The parameters with others, each replacing the one of the same name and location.
     *
     * @param list<ParameterDescription> $parameters
     * @param list<ParameterDescription> $others
     *
     * @return list<ParameterDescription>
     */
    public static function merge(array $parameters, array $others): array
    {
        foreach ($others as $other) {
            $replaced = false;

            foreach ($parameters as $index => $parameter) {
                if ($parameter->name === $other->name && $parameter->in === $other->in) {
                    $parameters[$index] = $other->in === ParameterLocation::Querystring ? $other->schema(self::combine($parameter->schema, $other->schema)) : $other;
                    $replaced = true;
                }
            }

            if (! $replaced) {
                $parameters[] = $other;
            }
        }

        return self::fold($parameters);
    }

    /**
     * Two object schemas as one: the properties of the second added, replacing those of the same name, and its
     * required keys.
     */
    public static function combine(Schema $schema, Schema $other): Schema
    {
        $properties = [...$schema->properties ?? [], ...$other->properties ?? []];
        $required = array_values(array_unique([...$schema->required ?? [], ...$other->required ?? []]));
        $combined = $properties === [] ? $schema : $schema->properties(...array_values($properties));

        return $required === [] ? $combined : $combined->required(...$required);
    }

    /**
     * A `querystring` parameter describes the whole query: the `query` parameters join its schema, since 3.2
     * forbids them next to it.
     *
     * @param list<ParameterDescription> $parameters
     *
     * @return list<ParameterDescription>
     */
    private static function fold(array $parameters): array
    {
        $index = null;

        foreach ($parameters as $candidate => $found) {
            if ($found->in === ParameterLocation::Querystring) {
                $index = $candidate;
            }
        }

        if ($index === null) {
            return $parameters;
        }

        $querystring = $parameters[$index];
        $kept = [];

        foreach ($parameters as $position => $parameter) {
            if ($parameter->in !== ParameterLocation::Query) {
                $kept[$position] = $parameter;

                continue;
            }

            $property = $parameter->schema->objectId($parameter->name);
            $property = $parameter->description === null ? $property : $property->description($parameter->description);
            $object = Schema::object()->properties($property);
            $querystring = $querystring->schema(self::combine($querystring->schema, $parameter->required ? $object->required($parameter->name) : $object));
        }

        $kept[$index] = $querystring;

        return array_values($kept);
    }

    /**
     * @param array<array-key, FieldDescription> $files
     *
     * @return array<string, list<string>> top-level property → content types
     */
    private static function encodings(array $files): array
    {
        $encodings = [];

        foreach ($files as $file) {
            if ($file->contentTypes !== []) {
                $property = explode('.', $file->name)[0];
                $encodings[$property] = array_values(array_unique([...$encodings[$property] ?? [], ...$file->contentTypes]));
            }
        }

        return $encodings;
    }
}
