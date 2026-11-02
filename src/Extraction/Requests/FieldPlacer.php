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
 * are added, replacing those of the same name, and their required keys too. Query parameters next to a `querystring`
 * (3.2) become its properties, with their description, their deprecation and their requirement.
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
     * The parameters with others, each replacing the one of the same name and location. A header, whatever the case
     * of its name, completes the one already there instead, under its first name: its schema replaces the first
     * one, its description, style and explode too when it has them, and it is required or deprecated when either is.
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
                if (self::same($parameter, $other)) {
                    $parameters[$index] = match ($other->in) {
                        ParameterLocation::Querystring => $other->schema(self::combine($parameter->schema, $other->schema)),
                        ParameterLocation::Header => self::header($parameter, $other),
                        default => $other,
                    };
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
     * The parameter of the same name and location (a header whatever the case of its name), or the property of the
     * `querystring` a query parameter is folded into, as a query parameter.
     *
     * @param list<ParameterDescription> $parameters
     */
    public static function find(ParameterDescription $parameter, array $parameters): ?ParameterDescription
    {
        foreach ($parameters as $existing) {
            if (self::same($existing, $parameter)) {
                return $existing;
            }
        }

        $index = $parameter->in === ParameterLocation::Query ? self::querystring($parameters) : null;
        $querystring = $index === null ? null : $parameters[$index];
        $property = $querystring?->schema->properties[$parameter->name] ?? null;

        if ($querystring === null || ! $property instanceof Schema) {
            return null;
        }

        return $parameter
            ->schema($property)
            ->description($property->description)
            ->required(in_array($parameter->name, $querystring->schema->required ?? [], true))
            ->deprecated($property->deprecated ?? false);
    }

    /**
     * The parameters with a parameter completed from the one `find()` gave, its requirement and its deprecation
     * stated: it replaces the parameter of the same name and location (a header whatever the case of its name), or
     * the property of the `querystring` it is folded into, its name kept at its place in `required` or left out.
     *
     * @param list<ParameterDescription> $parameters
     *
     * @return list<ParameterDescription>
     */
    public static function complete(array $parameters, ParameterDescription $parameter): array
    {
        $replaced = false;
        $completed = [];

        // Every parameter it matches gives way to it, at the place of the first one.
        foreach ($parameters as $existing) {
            if (! self::same($existing, $parameter)) {
                $completed[] = $existing;
            } elseif (! $replaced) {
                $completed[] = $parameter;
                $replaced = true;
            }
        }

        if ($replaced) {
            return $completed;
        }

        $index = $parameter->in === ParameterLocation::Query ? self::querystring($parameters) : null;

        if ($index === null) {
            return self::merge($parameters, [$parameter]);
        }

        $parameters[$index] = $parameters[$index]->schema(self::property($parameters[$index]->schema, $parameter, true));

        return array_values($parameters);
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
        $index = self::querystring($parameters);

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

            $querystring = $querystring->schema(self::property($querystring->schema, $parameter, false));
        }

        $kept[$index] = $querystring;

        return array_values($kept);
    }

    /**
     * The schema of a `querystring` with a query parameter as its property, replacing the one of the same name, with
     * its description and its deprecation. A required parameter puts its name in `required` (at its place when it
     * already is); a parameter whose requirement and deprecation are stated also leaves them out when it has none.
     */
    private static function property(Schema $schema, ParameterDescription $parameter, bool $stated): Schema
    {
        $name = $parameter->name;
        $property = $parameter->schema->objectId($name);
        $property = $parameter->description === null ? $property : $property->description($parameter->description);
        $property = match (true) {
            $parameter->deprecated => $property->deprecated(),
            $stated => $property->without('deprecated'),
            default => $property,
        };
        $schema = $schema->properties(...array_values([...$schema->properties ?? [], $name => $property]));
        $required = $schema->required ?? [];
        $required = match (true) {
            $parameter->required => in_array($name, $required, true) ? $required : [...$required, $name],
            $stated => array_values(array_diff($required, [$name])),
            default => $required,
        };

        return $required === [] ? $schema->without('required') : $schema->required(...$required);
    }

    /**
     * The index of the `querystring` the query parameters are folded into: the last one.
     *
     * @param list<ParameterDescription> $parameters
     */
    private static function querystring(array $parameters): ?int
    {
        $index = null;

        foreach ($parameters as $candidate => $found) {
            if ($found->in === ParameterLocation::Querystring) {
                $index = $candidate;
            }
        }

        return $index;
    }

    /**
     * A header completed by a later declaration of it, under its first name.
     */
    private static function header(ParameterDescription $header, ParameterDescription $other): ParameterDescription
    {
        return $header
            ->schema($other->schema)
            ->description($other->description ?? $header->description)
            ->required($header->required || $other->required)
            ->deprecated($header->deprecated || $other->deprecated)
            ->style($other->style ?? $header->style)
            ->explode($other->explode ?? $header->explode);
    }

    private static function same(ParameterDescription $parameter, ParameterDescription $other): bool
    {
        return $parameter->in === $other->in
            && ($parameter->name === $other->name || ($parameter->in === ParameterLocation::Header && strcasecmp($parameter->name, $other->name) === 0));
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
