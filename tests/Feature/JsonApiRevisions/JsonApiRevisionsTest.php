<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature\JsonApiRevisions;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Generation\GeneratedDocument;
use Ark4ne\OpenApi\Integrations\JsonApiV2\JsonApiV2Integration;
use Ark4ne\OpenApi\Tests\Feature\DocumentScenario;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiRevisions\ApiRevision;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiRevisions\ArticleController;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApiRevisions\MemberResource;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApiWriter\Enums\Version;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * One document per revision of `ark4ne/laravel-json-api` 2.1 (`documents.<name>.integrations.json_api.revision`):
 * the resources, the rules of the form requests (`Revised`) and the `include` parameter of that revision, the
 * changelog of the newer ones, a resource absent from the revision reported.
 */
#[CoversNothing]
#[Group('json-api-v2')]
#[Group('json-api-revisions')]
final class JsonApiRevisionsTest extends DocumentScenario
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        Schema::create('posts', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('title');
            $table->text('content');
            $table->string('status');
            $table->timestamps();
        });
    }

    protected function defineRoutes($router): void
    {
        $router->get('api/articles', [ArticleController::class, 'index'])->name('articles.index');
        $router->get('api/articles/{post}', [ArticleController::class, 'show'])->name('articles.show');
        $router->post('api/articles', [ArticleController::class, 'store'])->name('articles.store');
        $router->get('api/articles/{post}/preview', [ArticleController::class, 'preview'])->name('articles.preview');
        $router->get('api/members/{user}', [ArticleController::class, 'member'])->name('members.show');
    }

    protected function integrations(): array
    {
        return [new JsonApiV2Integration()];
    }

    /**
     * The names of the revisions: the provider runs before the groups are filtered, without the revisions of 2.0.
     *
     * @return iterable<string, array{string}>
     */
    public static function revisions(): iterable
    {
        foreach (['Initial', 'Labels', 'Members', 'Head'] as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('revisions')]
    public function test_the_documents_of_each_revision_are_those_expected(string $name): void
    {
        $revision = $name === 'Head' ? null : constant(ApiRevision::class . "::{$name}");
        self::assertTrue($revision === null || $revision instanceof ApiRevision);

        $this->assertExpectedDocuments(__DIR__ . "/{$name}", self::settings($revision));
    }

    public function test_the_resources_are_those_of_the_revision(): void
    {
        $initial = $this->written(ApiRevision::Initial);
        $labels = $this->written(ApiRevision::Labels);

        self::assertSame(['name', 'status'], array_keys((array) Schemas::at($initial, 'components', 'schemas', 'Article', 'properties', 'attributes', 'properties')));
        self::assertSame(['writer'], array_keys((array) Schemas::at($initial, 'components', 'schemas', 'Article', 'properties', 'relationships', 'properties')));
        self::assertSame(['label', 'summary'], array_keys((array) Schemas::at($labels, 'components', 'schemas', 'Article', 'properties', 'attributes', 'properties')));
        self::assertSame(['author'], array_keys((array) Schemas::at($labels, 'components', 'schemas', 'Article', 'properties', 'relationships', 'properties')));
    }

    public function test_the_head_is_documented_without_revision(): void
    {
        $head = $this->written(null);

        self::assertSame(['label', 'summary'], array_keys((array) Schemas::at($head, 'components', 'schemas', 'Article', 'properties', 'attributes', 'properties')));
    }

    public function test_the_rules_of_a_form_request_are_those_of_the_revision(): void
    {
        $body = ['paths', '/api/articles', 'post', 'requestBody', 'content', 'application/json', 'schema'];
        $initial = $this->written(ApiRevision::Initial);
        $labels = $this->written(ApiRevision::Labels);

        self::assertSame(['name', 'summary'], array_keys((array) Schemas::at($initial, ...[...$body, 'properties'])));
        self::assertSame(100, Schemas::at($initial, ...[...$body, 'properties', 'summary', 'maxLength']));
        self::assertSame(['label', 'summary'], array_keys((array) Schemas::at($labels, ...[...$body, 'properties'])));
        self::assertSame(500, Schemas::at($labels, ...[...$body, 'properties', 'summary', 'maxLength']));
    }

    public function test_the_rules_of_a_described_request_are_those_of_the_revision(): void
    {
        $names = static fn(array $written): array => array_map(static fn(mixed $parameter): mixed => is_array($parameter) ? $parameter['name'] ?? null : null, (array) Schemas::at($written, 'paths', '/api/articles/{post}/preview', 'get', 'parameters'));

        self::assertSame(['legacy'], $names($this->written(ApiRevision::Initial)));
        self::assertSame(['draft'], $names($this->written(ApiRevision::Labels)));
    }

    public function test_the_include_parameter_lists_the_relationships_of_the_revision_of_its_rule(): void
    {
        $initial = $this->parameters($this->written(ApiRevision::Initial));
        $labels = $this->parameters($this->written(ApiRevision::Labels));

        self::assertStringContainsString('`writer`', $initial['include']);
        self::assertStringNotContainsString('`author`', $initial['include']);
        self::assertStringContainsString('`author`', $labels['include']);
        // Built without revision, the rule validates the head, whatever the document.
        self::assertStringContainsString('`author`', $initial['include_head']);
    }

    public function test_a_revision_document_lists_the_newer_revisions(): void
    {
        self::assertSame("Articles.\n\nNewer revisions:\n\n- 2026-12-01: Names become labels\n- 2027-01-15: Members replace writers", Schemas::at($this->written(ApiRevision::Initial), 'info', 'description'));
        self::assertSame('Articles.', Schemas::at($this->written(ApiRevision::Members), 'info', 'description'));
        self::assertSame('Articles.', Schemas::at($this->written(null), 'info', 'description'));
    }

    public function test_an_operation_on_a_resource_absent_from_the_revision_is_reported(): void
    {
        $diagnostics = array_map(strval(...), $this->generated(ApiRevision::Initial)->context->diagnostics->all());

        // Its component is built once for the document, whatever the route: reported once, without route.
        self::assertCount(1, array_keys($diagnostics, '[warning] response: ' . MemberResource::class . ' does not exist at the revision 2026-09-01: exclude its routes from the document (routes.exclude)', true));
        self::assertSame([], array_values(array_filter(array_map(strval(...), $this->generated(ApiRevision::Labels)->context->diagnostics->all()), static fn(string $diagnostic): bool => str_contains($diagnostic, 'does not exist at the revision'))));
    }

    /**
     * @return array<string, mixed>
     */
    private static function settings(?ApiRevision $revision): array
    {
        return [
            'info' => ['title' => 'Scenario', 'version' => '1.0.0', 'description' => 'Articles.'],
            'integrations' => $revision === null ? [] : ['json_api' => ['revision' => $revision]],
        ];
    }

    private function generated(?ApiRevision $revision): GeneratedDocument
    {
        $configuration = Configuration::fromArray([
            'documents' => ['v1' => ['routes' => ['include' => null], ...self::settings($revision)]],
            'sampling' => ['examples' => false],
        ]);

        return $this->generator()->generate($configuration, 'v1');
    }

    /**
     * @return array<array-key, mixed>
     */
    private function written(?ApiRevision $revision): array
    {
        return $this->generated($revision)->openApi->toArray(Version::V3_1_2);
    }

    /**
     * The descriptions of the query parameters of the articles, by name.
     *
     * @param array<array-key, mixed> $written
     *
     * @return array<string, string>
     */
    private function parameters(array $written): array
    {
        $descriptions = [];

        foreach ((array) Schemas::at($written, 'paths', '/api/articles', 'get', 'parameters') as $parameter) {
            if (is_array($parameter) && is_string($parameter['name'] ?? null)) {
                $schema = $parameter['schema'] ?? [];
                $description = (is_array($schema) ? $schema['description'] ?? null : null) ?? $parameter['description'] ?? '';
                $descriptions[$parameter['name']] = is_string($description) ? $description : '';
            }
        }

        return $descriptions;
    }
}
