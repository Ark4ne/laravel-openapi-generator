<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApiV2;

use Ark4ne\JsonApi\Http\Rules\Fields;
use Ark4ne\JsonApi\Http\Rules\Includes;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Integrations\JsonApiV2\FieldsRuleMapper;
use Ark4ne\OpenApi\Integrations\JsonApiV2\IncludesRuleMapper;
use Ark4ne\OpenApi\Integrations\JsonApiV2\ResourceDefinitions;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Resources\PostResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Resources\UserResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types\NestedResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types\RenamedHookResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types\ShowcaseResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types\TagCollection;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types\UntypedNativeHolderResource;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use stdClass;

#[CoversClass(IncludesRuleMapper::class)]
#[CoversClass(FieldsRuleMapper::class)]
#[CoversClass(ResourceDefinitions::class)]
#[Group('json-api-v2')]
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

    public function test_the_include_example_takes_described_paths_only(): void
    {
        self::assertSame('tags', $this->map(new IncludesRuleMapper(), new Includes(NestedResource::class), 'include')->schema->example);
    }

    public function test_a_native_relationship_ends_its_path_and_a_polymorphic_one_follows_each_resource(): void
    {
        $field = $this->map(new IncludesRuleMapper(), new Includes(ShowcaseResource::class), 'include');

        self::assertSame(
            'Relationships to include, separated by commas: `subject`, `subject.user`, `subject.user.comments`, `subject.comments`, `subject.comments.user`, `subject.user.posts`, `subject.post`, `subject.post.user`, `author`.',
            $field->schema->description,
        );
    }

    public function test_a_resource_without_relationship_has_no_path(): void
    {
        Schemas::assertSame(['type' => 'string'], $this->map(new IncludesRuleMapper(), new Includes(TagCollection::class), 'include')->schema);
    }

    public function test_fields_has_one_property_per_type_the_definitions_reach(): void
    {
        $field = $this->map(new FieldsRuleMapper(), new Fields(PostResource::class), 'fields');

        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'post' => ['type' => 'string', 'description' => 'Fields of `post`, separated by commas: `title`, `content`, `status`, `user`, `comments`.'],
                'user' => ['type' => 'string', 'description' => 'Fields of `user`, separated by commas: `name`, `email`, `profile`, `profile.name`, `profile.initials`, `profile.verified`, `profile.hidden`, `profile.scores`, `profile.scores.rank`, `profile.scores.ratio`, `posts`, `comments`.'],
                'comment' => ['type' => 'string', 'description' => 'Fields of `comment`, separated by commas: `content`, `user`, `post`.'],
            ],
            'additionalProperties' => false,
        ], $field->schema);
        self::assertSame([FieldsRuleMapper::RULE], (new FieldsRuleMapper())->handles());
    }

    public function test_a_native_resource_has_no_known_fields_and_one_of_unknown_type_opens_the_object(): void
    {
        $showcase = $this->map(new FieldsRuleMapper(), new Fields(ShowcaseResource::class), 'fields')->schema;
        $holder = $this->map(new FieldsRuleMapper(), new Fields(UntypedNativeHolderResource::class), 'fields')->schema;

        Schemas::assertSame(['type' => 'string', 'description' => 'Fields of `native_authors`, separated by commas.'], $showcase->properties['native_authors'] ?? Schema::create());
        self::assertFalse($showcase->additionalProperties);
        Schemas::assertSame([
            'type' => 'object',
            'properties' => ['holder' => ['type' => 'string', 'description' => 'Fields of `holder`, separated by commas: `owner`.']],
            'additionalProperties' => ['type' => 'string'],
        ], $holder);
    }

    public function test_a_resource_the_package_cannot_compile_keeps_the_field_with_a_warning(): void
    {
        $context = $this->context();
        $includes = (new IncludesRuleMapper())->map(new RuleInvocation('include', Includes::class, [], new Includes(RenamedHookResource::class)), FieldDescription::create('include'), $context);
        $fields = (new FieldsRuleMapper())->map(new RuleInvocation('fields', Fields::class, [], new Fields(RenamedHookResource::class)), FieldDescription::create('fields'), $context);

        Schemas::assertSame(['type' => 'string'], $includes->schema);
        Schemas::assertSame(['type' => 'object'], $fields->schema);
        self::assertStringContainsString('include: cannot read the definition of ' . RenamedHookResource::class . ': ', self::diagnostics($context));
        self::assertStringContainsString('fields: cannot read the definition of ' . RenamedHookResource::class . ': ', self::diagnostics($context));
    }

    public function test_a_rule_without_its_resource_keeps_the_field_with_a_warning(): void
    {
        $context = $this->context();
        $fields = (new FieldsRuleMapper())->map(new RuleInvocation('fields', Fields::class, [], new stdClass()), FieldDescription::create('fields'), $context);
        $includes = (new IncludesRuleMapper())->map(new RuleInvocation('include', Includes::class, [], null), FieldDescription::create('include'), $context);

        Schemas::assertSame(['type' => 'object'], $fields->schema);
        Schemas::assertSame(['type' => 'string'], $includes->schema);
        self::assertStringContainsString('fields: cannot read the resource of ' . stdClass::class, self::diagnostics($context));
        self::assertStringContainsString('include: cannot read the resource of null', self::diagnostics($context));
    }

    private function map(IncludesRuleMapper|FieldsRuleMapper $mapper, object $rule, string $name): FieldDescription
    {
        return $mapper->map(new RuleInvocation($name, $rule::class, [], $rule), FieldDescription::create($name)->schema(Schema::create()), $this->context());
    }

    private static function diagnostics(GenerationContext $context): string
    {
        return implode("\n", array_map(strval(...), $context->diagnostics->all()));
    }
}
