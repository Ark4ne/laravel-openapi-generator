<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Integrations\JsonApi;

use Ark4ne\JsonApi\Resources\Skeleton;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;

/**
 * `new Includes(UserResource::class)`: a string of relationship paths separated by commas (`posts,posts.comments`);
 * the description lists the paths of the resource, a path never going back to a resource it went through; the
 * example takes the first two relationships it describes.
 */
final readonly class IncludesRuleMapper implements RuleMapper
{
    public const RULE = 'Ark4ne\JsonApi\Requests\Rules\Includes';

    public function handles(): array
    {
        return [self::RULE];
    }

    public function map(RuleInvocation $rule, FieldDescription $field, GenerationContext $context): FieldDescription
    {
        $schema = $field->schema->type('string');
        $skeleton = $rule->rule === null ? null : Skeletons::ofRule($rule->rule, $context, $rule->field);
        $paths = $skeleton === null ? [] : self::paths($skeleton, [$skeleton->for]);

        if ($paths === []) {
            return $field->schema($schema);
        }

        $description = 'Relationships to include, separated by commas: ' . implode(', ', array_map(static fn(string $path): string => "`{$path}`", $paths)) . '.';
        $example = implode(',', array_slice(array_values(array_filter($paths, static fn(string $path): bool => ! str_contains($path, '.'))), 0, 2));

        $schema = $schema->description($description);

        return $field->schema($context->configuration->sampling->examples ? $schema->example($example) : $schema);
    }

    /**
     * @param Skeleton<mixed> $skeleton
     * @param list<string> $through the resources of the path so far
     *
     * @return list<string>
     */
    private static function paths(Skeleton $skeleton, array $through, string $prefix = ''): array
    {
        $paths = [];

        foreach ($skeleton->relationships as $name => $relationship) {
            if (in_array($relationship->for, $through, true)) {
                continue;
            }

            $path = $prefix . $name;
            $paths = [...$paths, $path, ...self::paths($relationship, [...$through, $relationship->for], "{$path}.")];
        }

        return $paths;
    }
}
