<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApi;

use Ark4ne\JsonApi\Requests\Rules\Fields;
use Ark4ne\JsonApi\Requests\Rules\Includes;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Integrations\JsonApi\FieldsRuleMapper;
use Ark4ne\OpenApi\Integrations\JsonApi\IncludesRuleMapper;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Resources\PostResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Resources\UserResource;
use Ark4ne\OpenApi\Tests\Fixtures\Types\BrokenJsonApiResource;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(IncludesRuleMapper::class)]
#[CoversClass(FieldsRuleMapper::class)]
#[Group('json-api')]
final class JsonApiRuleMappersTest extends TestCase
{
    public function test_includes_lists_the_relationship_paths_without_going_back(): void
    {
        $field = $this->map(new IncludesRuleMapper(), new Includes(UserResource::class), 'include');

        Schemas::assertSame([
            'type' => 'string',
            'description' => 'Relationships to include, separated by commas: `posts`, `posts.comments`, `comments`, `comments.post`.',
            'example' => 'posts,comments',
        ], $field->schema);
        self::assertSame([IncludesRuleMapper::RULE], (new IncludesRuleMapper())->handles());
    }

    public function test_fields_has_one_property_per_type_the_resource_reaches(): void
    {
        $field = $this->map(new FieldsRuleMapper(), new Fields(PostResource::class), 'fields');

        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'post' => ['type' => 'string', 'description' => 'Fields of `post`, separated by commas: `title`, `content`, `status`.'],
                'user' => ['type' => 'string', 'description' => 'Fields of `user`, separated by commas: `name`, `email`, `profile`, `profile.name`, `profile.initials`, `profile.verified`, `profile.hidden`, `profile.scores.rank`, `profile.scores.ratio`.'],
                'comment' => ['type' => 'string', 'description' => 'Fields of `comment`, separated by commas: `content`.'],
            ],
            'additionalProperties' => false,
        ], $field->schema);
        self::assertSame([FieldsRuleMapper::RULE], (new FieldsRuleMapper())->handles());
    }

    public function test_a_resource_whose_schema_fails_keeps_the_field_with_a_warning(): void
    {
        $context = $this->context();
        $field = (new IncludesRuleMapper())->map(new RuleInvocation('include', Includes::class, [], new Includes(BrokenJsonApiResource::class)), FieldDescription::create('include'), $context);

        Schemas::assertSame(['type' => 'string'], $field->schema);
        self::assertStringContainsString('cannot read the schema of ' . BrokenJsonApiResource::class . ': broken', implode("\n", array_map(strval(...), $context->diagnostics->all())));
    }

    private function map(IncludesRuleMapper|FieldsRuleMapper $mapper, object $rule, string $name): FieldDescription
    {
        return $mapper->map(new RuleInvocation($name, $rule::class, [], $rule), FieldDescription::create($name)->schema(Schema::create()), $this->context());
    }
}
