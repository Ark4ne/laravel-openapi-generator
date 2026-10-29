<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApi;

use Ark4ne\JsonApi\Resources\Skeleton;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * `new Fields(UserResource::class)`: an object with one property per type the resource reaches (`fields[post]`),
 * each a string of fields separated by commas; written as a `deepObject` query parameter.
 */
final readonly class FieldsRuleMapper implements RuleMapper
{
    public const RULE = 'Ark4ne\JsonApi\Requests\Rules\Fields';

    public function handles(): array
    {
        return [self::RULE];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $skeleton = $rule->rule === null ? null : Skeletons::ofRule($rule->rule, $context, $rule->field);

        if ($skeleton === null) {
            return $field->schema($field->schema->type('object'));
        }

        $properties = [];

        foreach (self::types($skeleton) as $type => $fields) {
            $listed = implode(', ', array_map(static fn(string $name): string => "`{$name}`", $fields));
            $properties[] = Schema::string()->description("Fields of `{$type}`, separated by commas: {$listed}.")->objectId($type);
        }

        return $field->schema($field->schema->type('object')->properties(...$properties)->additionalProperties(false));
    }

    /**
     * The fields of each type the skeleton reaches, depth first as the rule reads them.
     *
     * @param Skeleton<mixed> $skeleton
     * @param array<string, list<string>> $types
     *
     * @return array<string, list<string>>
     */
    private static function types(Skeleton $skeleton, array $types = []): array
    {
        if (isset($types[$skeleton->type])) {
            return $types;
        }

        $types[$skeleton->type] = array_values($skeleton->fields);

        foreach ($skeleton->relationships as $relationship) {
            $types = self::types($relationship, $types);
        }

        return $types;
    }
}
