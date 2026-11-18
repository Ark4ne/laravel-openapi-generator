<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Strategies;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Extraction\Rules\FieldTree;
use Ark4ne\OpenApi\Extraction\Rules\SchemaTypes;
use Ark4ne\OpenApiWriter\Enums\ParameterStyle;
use Ark4ne\OpenApiWriter\Enums\Version;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * How nested query fields are written, `filter.*.name.*` being a string:
 *
 * - `all`: every level flattened, `filter[][name][]` of type string;
 * - `last`: the last list kept, `filter[][name]` of type `array<string>`;
 * - `none`: one parameter per top-level field, `filter` of type `array<object{name: array<string>}>`, objects as
 *   `deepObject`;
 * - `querystring`: the whole query as one `querystring` parameter (3.2), `none` before.
 *
 * A query field without type is a string. A flattened field is required only when no list is above it. With `all` and
 * `last`, an object left as one field (`fields`, an object of one property per type) is flattened too: one parameter
 * per property (`fields[posts]`), required when the object is and names it in `required`. An object whose keys are
 * not all known (no `properties`, open `additionalProperties`, a property that is a reference) stays one `deepObject`
 * parameter, with an info when it has properties.
 */
final readonly class BuiltInQueryStrategy implements QueryStrategy
{
    public const BY = ['all', 'last', 'none', 'querystring'];

    public function __construct(
        private string $by = 'all',
    ) {}

    public function parameters(array $fields, GenerationContext $context): array
    {
        $fields = array_values(array_filter($fields, static fn(FieldDescription $field): bool => ! $field->forbidden));

        return match (true) {
            $this->by === 'querystring' && $context->document->version->isAtLeast(Version::V3_2_0) => [
                ParameterDescription::querystring('query', FieldTree::object($fields)),
            ],
            $this->by === 'none', $this->by === 'querystring' => self::objects($fields),
            default => self::flattened($fields, $this->by === 'last', $context),
        };
    }

    /**
     * @param list<FieldDescription> $fields
     *
     * @return list<ParameterDescription>
     */
    private static function objects(array $fields): array
    {
        $parameters = [];

        foreach (FieldTree::top($fields) as $name => $property) {
            $schema = self::typed($property['schema']);
            $parameter = ParameterDescription::query($name, $schema)->required($property['required']);
            $parameters[] = SchemaTypes::of($schema) === 'object' ? $parameter->style(ParameterStyle::DeepObject)->explode() : $parameter;
        }

        return $parameters;
    }

    /**
     * @param list<FieldDescription> $fields
     *
     * @return list<ParameterDescription>
     */
    private static function flattened(array $fields, bool $keepLastList, GenerationContext $context): array
    {
        $names = array_map(static fn(FieldDescription $field): string => $field->name, $fields);
        $parameters = [];

        foreach ($fields as $field) {
            if (self::hasChildren($field->name, $names)) {
                continue;
            }

            $schema = self::typed(FieldTree::leaf($field));
            $segments = explode('.', $field->name);

            if ($keepLastList && count($segments) > 1 && $segments[array_key_last($segments)] === '*') {
                array_pop($segments);
                $schema = Schema::array()->items($schema);
            }

            $name = array_shift($segments) . implode('', array_map(static fn(string $segment): string => $segment === '*' ? '[]' : "[{$segment}]", $segments));
            array_push($parameters, ...self::flat($name, $schema, $field->required && ! str_contains($field->name, '*'), $context));
        }

        return $parameters;
    }

    /**
     * The parameter of a flattened field, or one per property of the object it is when all its keys are known.
     *
     * @return list<ParameterDescription>
     */
    private static function flat(string $name, Schema $schema, bool $required, GenerationContext $context): array
    {
        if (SchemaTypes::of($schema) !== 'object') {
            return [ParameterDescription::query($name, $schema)->required($required)];
        }

        $properties = $schema->properties ?? [];
        $open = $schema->additionalProperties !== null && $schema->additionalProperties !== false;
        $known = array_filter($properties, static fn(mixed $property): bool => $property instanceof Schema);

        if ($properties === [] || $open || count($known) !== count($properties)) {
            if ($properties !== []) {
                $context->diagnostics->info(Stage::Request, "the query parameter {$name} accepts keys it does not name, written as a deepObject");
            }

            return [ParameterDescription::query($name, $schema)->required($required)->style(ParameterStyle::DeepObject)->explode()];
        }

        $parameters = [];

        foreach ($known as $key => $property) {
            array_push($parameters, ...self::flat("{$name}[{$key}]", self::typed($property), $required && in_array($key, $schema->required ?? [], true), $context));
        }

        return $parameters;
    }

    /**
     * @param list<string> $names
     */
    private static function hasChildren(string $name, array $names): bool
    {
        foreach ($names as $other) {
            if (str_starts_with($other, "{$name}.")) {
                return true;
            }
        }

        return false;
    }

    private static function typed(Schema $schema): Schema
    {
        return $schema->type === null && $schema->enum === null && $schema->anyOf === null ? $schema->type('string') : $schema;
    }
}
