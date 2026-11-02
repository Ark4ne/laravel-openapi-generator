<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApiV2;

use Ark4ne\JsonApi\Definition\ResourceDefinition;
use Ark4ne\JsonApi\Http\Rules\Includes;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;

/**
 * `new Includes(UserResource::class)`: a string of relationship paths separated by commas (`posts,posts.comments`);
 * the description lists the paths the definitions reach through `related`, a path never going back to a resource it
 * went through and a relationship to native resources ending its path; the example takes the first two relationships
 * it describes.
 */
final readonly class IncludesRuleMapper implements RuleMapper
{
    public const RULE = 'Ark4ne\JsonApi\Http\Rules\Includes';

    /**
     * @param ResourceDefinitions $definitions shared with the format and the other mapper of the integration
     */
    public function __construct(
        private ResourceDefinitions $definitions = new ResourceDefinitions(),
    ) {}

    public function handles(): array
    {
        return [self::RULE];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $schema = $field->schema->type('string');
        $definitions = $this->definitions;
        $resource = $rule->rule instanceof Includes ? $rule->rule->resource() : null;
        $definition = $resource === null ? self::unknown($rule, $context) : $definitions->of($resource, $context, Stage::Request, $rule->field);
        $paths = $definition === null ? [] : self::paths($definition, [$definition->class], $definitions, $context, $rule->field);

        if ($paths === []) {
            return $field->schema($schema);
        }

        $description = 'Relationships to include, separated by commas: ' . implode(', ', array_map(static fn(string $path): string => "`{$path}`", $paths)) . '.';
        $example = implode(',', array_slice(array_values(array_filter($paths, static fn(string $path): bool => ! str_contains($path, '.'))), 0, 2));

        $schema = $schema->description($description);

        return $field->schema($context->configuration->sampling->examples ? $schema->example($example) : $schema);
    }

    private static function unknown(RuleInvocation $rule, GenerationContext $context): null
    {
        $context->diagnostics->warning(Stage::Request, "{$rule->field}: cannot read the resource of " . get_debug_type($rule->rule) . ', field described without its resource');

        return null;
    }

    /**
     * @param list<string> $through the resources of the path so far
     *
     * @return list<string>
     */
    private static function paths(ResourceDefinition $definition, array $through, ResourceDefinitions $definitions, GenerationContext $context, string $field, string $prefix = ''): array
    {
        $paths = [];

        foreach ($definition->relationships as $name => $relationship) {
            $related = array_values(array_diff($relationship->related, $through));

            if ($relationship->native === null && $related === []) {
                continue;
            }

            $path = $prefix . $name;
            $paths[] = $path;

            foreach ($related as $class) {
                $known = $definitions->of($class, $context, Stage::Request, $field);
                $nested = $known === null ? [] : self::paths($known, [...$through, $class], $definitions, $context, $field, "{$path}.");
                $paths = [...$paths, ...array_diff($nested, $paths)];
            }
        }

        return $paths;
    }
}
