<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApiV3;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Integrations\JsonApiV3\ResourceCheckPass;
use Ark4ne\OpenApi\Integrations\JsonApiV3\ResourceDefinitions;
use Ark4ne\OpenApi\Integrations\JsonApiV3\Revisions;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\ApiRevision;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\ArticleResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\MemberResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types\FailingHookResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types\LinksFailingResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types\PostResource;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;

/**
 * `ArticleResource` declares no `down` conversion for the schema of the `was()` of its `place` at Initial: the check
 * of the package finds it (`conversion-missing`) at that revision only.
 */
#[CoversClass(ResourceCheckPass::class)]
#[CoversClass(ResourceDefinitions::class)]
#[Group('json-api-v3')]
final class ResourceCheckPassTest extends JsonApiV3TestCase
{
    private const FINDING = ArticleResource::class . ": No down conversion from object to object for 'attributes.place' (response) at was(Initial): declare down:. (conversion-missing, revision 2026-09-01)";

    public function test_the_resources_read_for_a_document_are_those_it_covers_once_each_in_order(): void
    {
        $context = $this->documented(['revision' => ApiRevision::Initial]);
        $definitions = new ResourceDefinitions(new Revisions());

        $definitions->of(ArticleResource::class, $context, Stage::Response, 'a');
        $definitions->of(PostResource::class, $context, Stage::Response, 'b');
        $definitions->of(ArticleResource::class, $context, Stage::Response, 'c', null);
        // Left out: absent from the revision, or not compiled.
        $definitions->of(MemberResource::class, $context, Stage::Response, 'd');
        $definitions->of(FailingHookResource::class, $context, Stage::Response, 'e');

        self::assertSame([ArticleResource::class, PostResource::class], $definitions->covered($context));
        self::assertSame([], $definitions->covered($this->documented([])));
    }

    public function test_the_findings_of_the_revision_of_the_document_are_relayed_with_their_code_and_revision(): void
    {
        $context = $this->checked(['revision' => ApiRevision::Initial], ArticleResource::class);

        self::assertContains('[warning] pass: ' . self::FINDING, $this->lines($context));
    }

    public function test_the_findings_of_another_revision_are_left_out(): void
    {
        self::assertSame([], $this->lines($this->checked(['revision' => ApiRevision::Labels], ArticleResource::class)));
        self::assertSame([], $this->lines($this->checked([], ArticleResource::class)));
    }

    public function test_a_finding_already_relayed_by_the_compilation_is_not_repeated(): void
    {
        $lines = array_map(strval(...), $this->checked([], LinksFailingResource::class)->diagnostics->all());

        self::assertCount(1, array_filter($lines, static fn(string $line): bool => str_contains($line, '(hook-failure')));
        self::assertStringStartsWith('[warning] response: ' . LinksFailingResource::class . ': toLinks() threw', implode("\n", $lines));
    }

    public function test_the_check_of_the_application_is_resolved_from_its_container(): void
    {
        $this->application()->bind(ResourceCheckPass::CHECKER_CLASS, static fn(): never => throw new RuntimeException('no checker'));

        $context = $this->checked(['revision' => ApiRevision::Initial], ArticleResource::class, withContainer: true);

        self::assertSame(['[warning] pass: cannot check the JSON:API resources: no checker'], $this->lines($context));
    }

    public function test_the_application_checker_finds_what_the_package_checker_finds(): void
    {
        self::assertTrue($this->application()->bound(ResourceCheckPass::CHECKER_CLASS));
        self::assertContains('[warning] pass: ' . self::FINDING, $this->lines($this->checked(['revision' => ApiRevision::Initial], ArticleResource::class, withContainer: true)));
    }

    public function test_a_document_without_resource_is_not_checked_and_the_document_is_unchanged(): void
    {
        $context = $this->documented([]);
        $document = DocumentDescription::create();

        self::assertSame($document, (new ResourceCheckPass(new ResourceDefinitions(), new Revisions()))->process($document, $context));
        self::assertSame([], $this->lines($context));
    }

    /**
     * A document at the settings, `$resource` read for it, then checked.
     *
     * @param array<string, mixed> $settings
     * @param class-string $resource
     */
    private function checked(array $settings, string $resource, bool $withContainer = false): GenerationContext
    {
        $context = $this->documented($settings);
        $revisions = new Revisions();
        $definitions = new ResourceDefinitions($revisions);
        $definitions->of($resource, $context, Stage::Response, 'read');

        (new ResourceCheckPass($definitions, $revisions, $withContainer ? $this->application() : null))->process(DocumentDescription::create(), $context);

        return $context;
    }

    private function application(): Application
    {
        self::assertNotNull($this->app);

        return $this->app;
    }

    /**
     * The diagnostics of the pass (`[level] pass: …`), one per line.
     *
     * @return list<string>
     */
    private function lines(GenerationContext $context): array
    {
        return array_values(array_filter(array_map(strval(...), $context->diagnostics->all()), static fn(string $line): bool => str_contains($line, '] pass: ')));
    }
}
