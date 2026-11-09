<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApiV2;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Configuration\InfoConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Integrations\JsonApiV2\Revisions;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiRevisions\ApiRevision;
use Ark4ne\OpenApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(Revisions::class)]
#[Group('json-api-v2')]
#[Group('json-api-revisions')]
final class RevisionsTest extends TestCase
{
    public function test_the_revision_of_a_document_is_read_from_its_settings(): void
    {
        self::assertSame(ApiRevision::Labels, (new Revisions())->of($this->documented(['revision' => ApiRevision::Labels])));
    }

    public function test_a_document_without_revision_documents_the_head(): void
    {
        $context = $this->documented(null);

        self::assertNull((new Revisions())->of($context));
        self::assertNull((new Revisions())->of($this->documented([])));
        self::assertDiagnostics([], $context);
    }

    public function test_an_invalid_revision_is_reported_once_and_the_head_documented(): void
    {
        $context = $this->documented(['revision' => '2026-12-01']);
        $revisions = new Revisions();

        self::assertNull($revisions->of($context));
        self::assertNull($revisions->of($context));
        self::assertDiagnostics(['[warning] configuration: documents.v1.integrations.json_api.revision: expected a case of an enum implementing Ark4ne\JsonApi\Definition\Revision, got "2026-12-01"; the head documented'], $context);
    }

    public function test_another_setting_is_reported(): void
    {
        $context = $this->documented(['revision' => ApiRevision::Initial, 'revison' => ApiRevision::Labels]);

        self::assertSame(ApiRevision::Initial, (new Revisions())->of($context));
        self::assertDiagnostics(['[warning] configuration: documents.v1.integrations.json_api.revison: unknown setting, ignored (known: revision)'], $context);
    }

    public function test_the_package_supports_revisions_from_2_1(): void
    {
        self::assertTrue(Revisions::supported());
    }

    public function test_a_revision_is_named_as_a_client_sends_it(): void
    {
        self::assertSame('2026-12-01', Revisions::name(ApiRevision::Labels));
    }

    public function test_the_changelog_lists_the_newer_revisions_oldest_first(): void
    {
        self::assertSame("Newer revisions:\n\n- 2026-12-01: Names become labels\n- 2027-01-15: Members replace writers", Revisions::changelog(ApiRevision::Initial));
        self::assertSame("Newer revisions:\n\n- 2027-01-15: Members replace writers", Revisions::changelog(ApiRevision::Labels));
    }

    public function test_the_newest_revision_and_the_head_have_no_changelog(): void
    {
        self::assertNull(Revisions::changelog(ApiRevision::Members));
        self::assertNull(Revisions::changelog(null));
    }

    /**
     * @param array<string, mixed>|null $settings the `json_api` settings, `null` for none
     */
    private function documented(?array $settings): GenerationContext
    {
        $document = new DocumentConfiguration('v1', new InfoConfiguration('API', '1'), integrations: $settings === null ? [] : ['json_api' => $settings]);

        return GenerationContext::for(new Configuration(['v1' => $document]), $document);
    }
}
