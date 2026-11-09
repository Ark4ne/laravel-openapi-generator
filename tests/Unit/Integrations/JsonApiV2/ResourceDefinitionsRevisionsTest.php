<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApiV2;

use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Integrations\JsonApiV2\ResourceDefinitions;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiRevisions\ApiRevision;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiRevisions\ArticleResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiRevisions\MemberResource;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV2\Types\FailingHookResource;
use Ark4ne\OpenApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(ResourceDefinitions::class)]
#[Group('json-api-v2')]
#[Group('json-api-revisions')]
final class ResourceDefinitionsRevisionsTest extends TestCase
{
    public function test_a_resource_is_read_once_per_revision(): void
    {
        $context = $this->context();
        $definitions = new ResourceDefinitions();

        $initial = $definitions->of(ArticleResource::class, $context, Stage::Response, 'articles', ApiRevision::Initial);
        $head = $definitions->of(ArticleResource::class, $context, Stage::Response, 'articles', null);

        self::assertSame($initial, $definitions->of(ArticleResource::class, $context, Stage::Response, 'articles', ApiRevision::Initial));
        self::assertSame(['name', 'status'], array_keys($initial->attributes ?? []));
        self::assertSame(['label', 'summary'], array_keys($head->attributes ?? []));
    }

    public function test_a_resource_absent_from_the_revision_is_reported_once(): void
    {
        $context = $this->context();
        $definitions = new ResourceDefinitions();

        self::assertNull($definitions->of(MemberResource::class, $context, Stage::Response, 'members', ApiRevision::Initial));
        self::assertNull($definitions->of(MemberResource::class, $context, Stage::Response, 'members', ApiRevision::Initial));
        self::assertNotNull($definitions->of(MemberResource::class, $context, Stage::Response, 'members', null));
        self::assertDiagnostics(['[warning] response: ' . MemberResource::class . ' does not exist at the revision 2026-09-01: exclude its routes from the document (routes.exclude)'], $context);
    }

    public function test_the_findings_of_a_resource_are_relayed_once_whatever_its_revisions(): void
    {
        $context = $this->context();
        $definitions = new ResourceDefinitions();

        $definitions->of(FailingHookResource::class, $context, Stage::Response, 'hook', ApiRevision::Initial);
        $definitions->of(FailingHookResource::class, $context, Stage::Response, 'hook', null);
        $definitions->of(FailingHookResource::class, $context, Stage::Response, 'hook', ApiRevision::Labels);

        $relayed = array_filter(array_map(strval(...), $context->diagnostics->all()), static fn(string $diagnostic): bool => str_contains($diagnostic, '(hook-failure)'));

        self::assertCount(1, $relayed);
    }
}
