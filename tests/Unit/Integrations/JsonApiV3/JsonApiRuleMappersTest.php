<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApiV3;

use Ark4ne\JsonApi\Http\Rules\Fields;
use Ark4ne\JsonApi\Http\Rules\Includes;
use Ark4ne\OpenApi\Description\FieldDescription;
use Ark4ne\OpenApi\Extraction\Rules\RuleInvocation;
use Ark4ne\OpenApi\Extraction\Rules\RuleMapper;
use Ark4ne\OpenApi\Integrations\JsonApiV3\FieldsRuleMapper;
use Ark4ne\OpenApi\Integrations\JsonApiV3\IncludesRuleMapper;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\ApiRevision;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\ArticleResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types\CommentResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types\FailingHookResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types\PolymorphicResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types\RenderFailingResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types\UntypedNativeHolderResource;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Illuminate\Contracts\Validation\ValidationRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use stdClass;

#[CoversClass(IncludesRuleMapper::class)]
#[CoversClass(FieldsRuleMapper::class)]
#[Group('json-api-v3')]
final class JsonApiRuleMappersTest extends JsonApiV3TestCase
{
    public function test_the_include_paths_are_those_of_the_revision_of_the_rule(): void
    {
        self::assertSame('Relationships to include, separated by commas: `writer`.', $this->map(new IncludesRuleMapper(), new Includes(ArticleResource::class, revision: ApiRevision::Initial))->schema->description);
        self::assertSame('Relationships to include, separated by commas: `author`.', $this->map(new IncludesRuleMapper(), new Includes(ArticleResource::class))->schema->description);
    }

    public function test_the_fields_of_each_type_are_those_of_the_revision_of_the_rule(): void
    {
        // The members of `place` are those of the schema its `was()` puts in force at the revision (`town`).
        Schemas::assertSame([
            'type' => 'object',
            'properties' => [
                'articles' => ['type' => 'string', 'description' => 'Fields of `articles`, separated by commas: `name`, `status`, `notes`, `place`, `place.town`, `writer`.'],
                'writers' => ['type' => 'string', 'description' => 'Fields of `writers`, separated by commas: `name`.'],
            ],
            'additionalProperties' => false,
        ], $this->map(new FieldsRuleMapper(), new Fields(ArticleResource::class, ApiRevision::Initial))->schema);
        self::assertSame(['articles', 'members'], array_keys($this->map(new FieldsRuleMapper(), new Fields(ArticleResource::class))->schema->properties ?? []));
    }

    public function test_includes_lists_a_relationship_back_to_a_resource_of_the_path_without_following_it(): void
    {
        self::assertSame('Relationships to include, separated by commas: `post`, `post.comments`, `parent`.', $this->map(new IncludesRuleMapper(), new Includes(CommentResource::class))->schema->description);
    }

    public function test_a_native_relationship_ends_its_path_and_a_resource_without_relationship_has_none(): void
    {
        self::assertStringContainsString('`author`', $this->map(new IncludesRuleMapper(), new Includes(PolymorphicResource::class))->schema->description ?? '');
        Schemas::assertSame(['type' => 'string'], $this->map(new IncludesRuleMapper(), new Includes(RenderFailingResource::class))->schema);
    }

    public function test_a_native_resource_has_no_known_fields_and_one_of_unknown_type_opens_the_object(): void
    {
        $polymorphic = $this->map(new FieldsRuleMapper(), new Fields(PolymorphicResource::class))->schema;

        Schemas::assertSame(['type' => 'string', 'description' => 'Fields of `native_authors`, separated by commas.'], self::at($polymorphic, 'native_authors'));
        Schemas::assertSame([
            'type' => 'object',
            'properties' => ['holders' => ['type' => 'string', 'description' => 'Fields of `holders`, separated by commas: `owner`.']],
            'additionalProperties' => ['type' => 'string'],
        ], $this->map(new FieldsRuleMapper(), new Fields(UntypedNativeHolderResource::class))->schema);
    }

    public function test_a_resource_the_package_cannot_compile_keeps_the_field_with_a_warning(): void
    {
        Schemas::assertSame(['type' => 'string'], $this->map(new IncludesRuleMapper(), new Includes(FailingHookResource::class))->schema);
        Schemas::assertSame(['type' => 'object'], $this->map(new FieldsRuleMapper(), new Fields(FailingHookResource::class))->schema);
        self::assertSame(2, substr_count($this->diagnostics(), 'field: cannot read the definition of ' . FailingHookResource::class . ': '));
    }

    public function test_a_rule_without_its_resource_keeps_the_field_with_a_warning(): void
    {
        $fields = (new FieldsRuleMapper())->map(new RuleInvocation('fields', Fields::class, [], new stdClass()), FieldDescription::create('fields'), $this->ctx());
        $includes = (new IncludesRuleMapper())->map(new RuleInvocation('include', Includes::class, [], null), FieldDescription::create('include'), $this->ctx());

        Schemas::assertSame(['type' => 'object'], $fields->schema);
        Schemas::assertSame(['type' => 'string'], $includes->schema);
        self::assertStringContainsString('fields: cannot read the resource of ' . stdClass::class, $this->diagnostics());
        self::assertStringContainsString('include: cannot read the resource of null', $this->diagnostics());
    }

    public function test_the_example_takes_the_first_two_relationships_when_the_examples_are_on(): void
    {
        $context = $this->context();
        $rule = new Includes(PolymorphicResource::class);

        self::assertSame('subject,author', (new IncludesRuleMapper())->map(new RuleInvocation('include', $rule::class, [], $rule), FieldDescription::create('include'), $context)->schema->example);
    }

    private function map(RuleMapper $mapper, ValidationRule $rule): FieldDescription
    {
        return $mapper->map(new RuleInvocation('field', $rule::class, [], $rule), FieldDescription::create('field'), $this->ctx());
    }
}
