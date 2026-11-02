<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApiV2;

use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Http\Rules\Fields;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * `new Fields(UserResource::class)`: an object with one property per type the definitions reach through `related`
 * (`fields[post]`), each a string of the fields of the definition (`fields()`) separated by commas; written as a
 * `deepObject` query parameter. A native resource has no known fields; one whose type cannot be read without model
 * opens the object to any type.
 */
final readonly class FieldsRuleMapper implements RuleMapper
{
    public const RULE = 'Ark4ne\JsonApi\Http\Rules\Fields';

    public function handles(): array
    {
        return [self::RULE];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $definitions = new ResourceDefinitions();
        $resource = $rule->rule instanceof Fields ? $rule->rule->resource() : null;

        if ($resource === null) {
            $context->diagnostics->warning(Stage::Request, "{$rule->field}: cannot read the resource of " . get_debug_type($rule->rule) . ', field described without its resource');
        }

        $definition = $resource === null ? null : $definitions->of($resource, $context, Stage::Request, $rule->field);

        if ($definition === null) {
            return $field->schema($field->schema->type('object'));
        }

        $untyped = false;
        $properties = [];

        foreach (self::types($definition, $definitions, $context, $rule->field, untyped: $untyped) as $type => $fields) {
            $description = $fields === null
                ? "Fields of `{$type}`, separated by commas."
                : "Fields of `{$type}`, separated by commas: " . implode(', ', array_map(static fn(string $name): string => "`{$name}`", $fields)) . '.';
            $properties[] = Schema::string()->description($description)->objectId($type);
        }

        return $field->schema($field->schema->type('object')->properties(...$properties)->additionalProperties($untyped ? Schema::string() : false));
    }

    /**
     * The fields of each type the definition reaches, depth first as the rule reads them, the first definition met
     * winning for a type; `null` for a native resource.
     *
     * @param array<string, list<string>|null> $types
     * @param bool $untyped set when a native resource of unknown type is met
     *
     * @return array<string, list<string>|null>
     */
    private static function types(ResourceDefinition $definition, ResourceDefinitions $definitions, GenerationContext $context, string $field, array $types = [], bool &$untyped = false): array
    {
        if (array_key_exists($definition->type, $types)) {
            return $types;
        }

        $types[$definition->type] = $definition->fields();

        foreach ($definition->relationships as $relationship) {
            if ($relationship->native !== null) {
                $type = ResourceDefinitions::nativeType($relationship->native);
                $untyped = $untyped || $type === null;

                if ($type !== null && ! array_key_exists($type, $types)) {
                    $types[$type] = null;
                }
            }

            foreach ($relationship->related as $related) {
                $known = $definitions->of($related, $context, Stage::Request, $field);
                $types = $known === null ? $types : self::types($known, $definitions, $context, $field, $types, $untyped);
            }
        }

        return $types;
    }
}
