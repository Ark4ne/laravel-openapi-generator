<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApiV3;

use Ark4ne\ApiRevisions\Revisions\RevisionScope;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Integrations\BuiltInIntegrations;
use Ark4ne\OpenApi\Integrations\JsonApi\JsonApiIntegration;
use Ark4ne\OpenApi\Integrations\JsonApiV2\JsonApiV2Integration;
use Ark4ne\OpenApi\Integrations\JsonApiV3\FieldsRuleMapper;
use Ark4ne\OpenApi\Integrations\JsonApiV3\IncludesRuleMapper;
use Ark4ne\OpenApi\Integrations\JsonApiV3\JsonApiErrorBodies;
use Ark4ne\OpenApi\Integrations\JsonApiV3\JsonApiResourceFormat;
use Ark4ne\OpenApi\Integrations\JsonApiV3\JsonApiV3Integration;
use Ark4ne\OpenApi\Integrations\JsonApiV3\Revisions;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\ApiRevision;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\OtherRevision;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(JsonApiV3Integration::class)]
#[CoversClass(Revisions::class)]
#[CoversClass(BuiltInIntegrations::class)]
#[Group('json-api-v3')]
final class JsonApiV3IntegrationTest extends JsonApiV3TestCase
{
    public function test_it_is_available_when_the_package_is_installed_in_3_x_and_registers_its_classes(): void
    {
        $integration = new JsonApiV3Integration();

        self::assertTrue($integration->available());
        self::assertSame('json_api', $integration->key());
        self::assertEquals([new JsonApiResourceFormat()], $integration->resourceFormats());
        self::assertEquals([new IncludesRuleMapper(), new FieldsRuleMapper()], $integration->ruleMappers());
        self::assertSame([], $integration->passes($this->ctx()));
    }

    public function test_the_1_x_and_2_x_integrations_are_not_available_with_3_x(): void
    {
        self::assertFalse((new JsonApiIntegration())->available());
        self::assertFalse((new JsonApiV2Integration())->available());
    }

    public function test_the_json_api_errors_are_opt_in(): void
    {
        self::assertNull((new JsonApiV3Integration())->errorBodies());
        self::assertInstanceOf(JsonApiErrorBodies::class, (new JsonApiV3Integration(errors: true))->errorBodies());
    }

    public function test_the_built_in_integrations_list_the_three_json_api_integrations_with_their_options(): void
    {
        self::assertEquals([new JsonApiIntegration(), new JsonApiV2Integration(), new JsonApiV3Integration()], BuiltInIntegrations::all());
        self::assertEquals([new JsonApiIntegration(errors: true), new JsonApiV2Integration(errors: true), new JsonApiV3Integration(errors: true)], BuiltInIntegrations::all(jsonApiErrors: true));
    }

    public function test_the_revision_of_a_document_is_read_from_its_settings(): void
    {
        $context = $this->documented(['revision' => ApiRevision::Labels]);

        self::assertSame(ApiRevision::Labels, (new Revisions())->of($context));
        self::assertSame('', $this->diagnosticsOf($context));
    }

    public function test_a_document_without_revision_documents_the_head(): void
    {
        self::assertNull((new Revisions())->of($this->documented([])));
    }

    public function test_an_invalid_revision_or_another_setting_is_reported_and_the_head_documented(): void
    {
        $context = $this->documented(['revision' => '2026-12-01', 'version' => 2]);

        self::assertNull((new Revisions())->of($context));
        self::assertSame(implode("\n", [
            '[warning] configuration: documents.v1.integrations.json_api.version: unknown setting, ignored (known: revision)',
            '[warning] configuration: documents.v1.integrations.json_api.revision: expected a case of ' . ApiRevision::class . ', got "2026-12-01"; the head documented',
        ]), $this->diagnosticsOf($context));
    }

    public function test_a_revision_of_another_enum_is_reported_and_the_head_documented(): void
    {
        $context = $this->documented(['revision' => OtherRevision::First]);

        self::assertNull((new Revisions())->of($context));
        self::assertSame('[warning] configuration: documents.v1.integrations.json_api.revision: expected a case of ' . ApiRevision::class . ', got ' . OtherRevision::class . '; the head documented', $this->diagnosticsOf($context));
    }

    public function test_a_revision_without_revision_enum_in_the_application_is_reported_and_the_head_documented(): void
    {
        $context = $this->documented(['revision' => ApiRevision::Labels]);

        self::assertNull((new Revisions(static fn(): ?string => null))->of($context));
        self::assertSame('[warning] configuration: documents.v1.integrations.json_api.revision: the application configures no revision enum (revisions.enum); the head documented', $this->diagnosticsOf($context));
    }

    public function test_the_revision_is_read_once_per_context(): void
    {
        $context = $this->documented(['revision' => 'invalid']);
        $revisions = new Revisions();

        $revisions->of($context);
        $revisions->of($context);

        self::assertCount(1, $context->diagnostics->all());
    }

    public function test_every_request_is_prepared_at_the_revision_of_the_document_the_head_included(): void
    {
        $integration = new JsonApiV3Integration();

        $integration->prepareRequest(Request::create('/'), $this->documented(['revision' => ApiRevision::Initial]));
        self::assertSame(ApiRevision::Initial, RevisionScope::get()->current(ApiRevision::class));

        $integration->prepareRequest(Request::create('/'), $this->documented([]));
        self::assertSame(ApiRevision::Members, RevisionScope::get()->current(ApiRevision::class));
    }

    public function test_the_rules_are_left_as_they_are(): void
    {
        $rules = ['name' => 'required'];

        self::assertSame($rules, (new JsonApiV3Integration())->prepareRules($rules, Request::create('/'), $this->ctx()));
    }

    public function test_a_document_lists_the_revisions_newer_than_its_own(): void
    {
        $integration = new JsonApiV3Integration();

        self::assertSame("Newer revisions:\n\n- 2026-12-01: Names become labels\n- 2027-01-15: Members replace writers", $integration->describe($this->documented(['revision' => ApiRevision::Initial])));
        self::assertNull($integration->describe($this->documented(['revision' => ApiRevision::Members])));
        self::assertNull($integration->describe($this->documented([])));
        self::assertSame('2026-12-01', Revisions::name(ApiRevision::Labels));
    }

    private function diagnosticsOf(GenerationContext $context): string
    {
        return implode("\n", array_map(strval(...), $context->diagnostics->all()));
    }
}
