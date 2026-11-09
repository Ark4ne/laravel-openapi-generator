<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApiV2;

use Ark4ne\JsonApi\Context\JsonApiContext;
use Ark4ne\JsonApi\Http\Rules\Revised;
use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Configuration\InfoConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Integrations\JsonApiV2\JsonApiV2Integration;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiRevisions\ApiRevision;
use Ark4ne\OpenApi\Tests\TestCase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * The integration as a document integration: what it does at the revision of a document.
 */
#[CoversClass(JsonApiV2Integration::class)]
#[Group('json-api-v2')]
#[Group('json-api-revisions')]
final class JsonApiV2RevisionsTest extends TestCase
{
    public function test_its_settings_are_those_of_json_api(): void
    {
        self::assertSame('json_api', (new JsonApiV2Integration())->key());
    }

    public function test_a_request_is_prepared_at_the_revision_of_the_document(): void
    {
        $request = Request::create('/');

        (new JsonApiV2Integration())->prepareRequest($request, $this->documented(ApiRevision::Labels));

        self::assertSame(ApiRevision::Labels, JsonApiContext::revisionOf($request));
    }

    public function test_a_request_of_the_head_is_left_as_is(): void
    {
        $request = Request::create('/');

        (new JsonApiV2Integration())->prepareRequest($request, $this->documented(null));

        self::assertNull(JsonApiContext::revisionOf($request));
        self::assertSame([], $request->attributes->all());
    }

    public function test_the_revised_rules_are_resolved_at_the_revision_of_the_document(): void
    {
        $rules = [
            'name' => Revised::rules('required|string')->until(ApiRevision::Initial),
            'label' => Revised::rules('required|string')->since(ApiRevision::Labels),
            'summary' => Revised::rules('string|max:500')->was(ApiRevision::Initial, rules: 'string|max:100'),
            'plain' => 'integer',
        ];
        $integration = new JsonApiV2Integration();

        self::assertSame(['name' => 'required|string', 'summary' => 'string|max:100', 'plain' => 'integer'], $integration->prepareRules($rules, Request::create('/'), $this->documented(ApiRevision::Initial)));
        self::assertSame(['label' => 'required|string', 'summary' => 'string|max:500', 'plain' => 'integer'], $integration->prepareRules($rules, Request::create('/'), $this->documented(null)));
    }

    public function test_the_document_of_a_revision_lists_the_newer_ones(): void
    {
        $integration = new JsonApiV2Integration();

        self::assertSame("Newer revisions:\n\n- 2027-01-15: Members replace writers", $integration->describe($this->documented(ApiRevision::Labels)));
        self::assertNull($integration->describe($this->documented(null)));
    }

    public function test_it_adds_no_pass(): void
    {
        self::assertSame([], (new JsonApiV2Integration())->passes($this->documented(ApiRevision::Labels)));
    }

    private function documented(?ApiRevision $revision): GenerationContext
    {
        $document = new DocumentConfiguration('v1', new InfoConfiguration('API', '1'), integrations: $revision === null ? [] : ['json_api' => ['revision' => $revision]]);

        return GenerationContext::for(new Configuration(['v1' => $document]), $document);
    }
}
