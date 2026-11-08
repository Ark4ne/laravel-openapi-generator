<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature\Laravel;

use Ark4ne\OpenApi\Laravel\Commands\InspectCommand;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\ResponseController;
use Ark4ne\OpenApi\Tests\Fixtures\Types\UntypedController;
use Ark4ne\OpenApi\Tests\Fixtures\Variants\ApiRevision;
use Illuminate\Contracts\Config\Repository;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InspectCommand::class)]
final class InspectCommandTest extends LaravelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $config = $this->app?->make(Repository::class);
        self::assertInstanceOf(Repository::class, $config);
        $config->set('openapi.documents.v1.routes.include', ['api/*']);
        $this->router()->get('api/articles/{article}', [ResponseController::class, 'show'])->name('articles.show');
        $this->router()->get('api/ping', [UntypedController::class, 'ping'])->name('ping');
        $this->router()->get('web/home', [UntypedController::class, 'ping'])->name('home');
    }

    public function test_a_route_is_found_by_its_name(): void
    {
        $this->command('openapi:inspect', ['route' => 'articles.show'])
            ->expectsOutputToContain('GET /api/articles/{article}')
            ->expectsOutputToContain('articles.show')
            ->expectsOutputToContain('article (path, required)')
            ->expectsOutputToContain('200 OK: application/json')
            ->assertExitCode(0);
    }

    public function test_a_route_is_found_by_its_method_and_uri(): void
    {
        $this->command('openapi:inspect', ['route' => 'GET api/ping'])
            ->expectsOutputToContain('GET /api/ping')
            ->expectsOutputToContain('Ping the API.')
            ->expectsOutputToContain('no return type, declare the responses with #[Response]')
            ->assertExitCode(0);
    }

    public function test_a_route_is_found_by_its_uri(): void
    {
        $this->command('openapi:inspect', ['route' => '/api/ping'])->expectsOutputToContain('GET /api/ping')->assertExitCode(0);
    }

    public function test_a_named_route_with_an_optional_parameter_shows_both_of_its_paths(): void
    {
        $this->router()->get('api/users/{user?}', [UntypedController::class, 'ping'])->name('users');

        $this->command('openapi:inspect', ['route' => 'users'])
            ->expectsOutputToContain('GET /api/users/{user}')
            ->expectsOutputToContain('GET /api/users ')
            ->assertExitCode(0);
    }

    public function test_the_diagnostics_recorded_before_a_path_was_renamed_are_shown(): void
    {
        $this->router()->get('api/items/{item}', [UntypedController::class, 'ping']);
        $this->router()->put('api/items/{id}', [UntypedController::class, 'ping']);

        $this->command('openapi:inspect', ['route' => 'PUT api/items/{item}'])
            ->expectsOutputToContain('PUT /api/items/{item}')
            ->expectsOutputToContain('no return type, declare the responses with #[Response]')
            ->assertExitCode(0);
    }

    public function test_a_route_out_of_the_document_is_reported(): void
    {
        $this->command('openapi:inspect', ['route' => 'home'])
            ->expectsOutputToContain('home is not in the document v1')
            ->assertExitCode(1);
    }

    public function test_a_document_with_variants_is_inspected_at_its_first_variant(): void
    {
        $this->variants(head: false);

        $this->command('openapi:inspect', ['route' => 'home'])
            ->expectsOutputToContain('home is not in the document v1 [2026-09-01]')
            ->assertExitCode(1);
    }

    public function test_the_variant_option_chooses_the_variant_inspected(): void
    {
        $this->variants(head: 'latest');

        $this->command('openapi:inspect', ['route' => 'home', '--variant' => 'latest'])
            ->expectsOutputToContain('home is not in the document v1 [latest]')
            ->assertExitCode(1);
        $this->command('openapi:inspect', ['route' => 'articles.show', '--variant' => '2026-12-01'])->assertExitCode(0);
    }

    public function test_an_unknown_variant_is_an_invalid_call(): void
    {
        $this->variants(head: false);

        $this->command('openapi:inspect', ['route' => 'ping', '--variant' => 'latest'])
            ->expectsOutputToContain('--variant: expected one of 2026-09-01, 2026-12-01, got "latest"')
            ->assertExitCode(2);
    }

    public function test_the_variant_option_needs_a_document_with_variants(): void
    {
        $this->command('openapi:inspect', ['route' => 'ping', '--variant' => '2026-12-01'])->assertExitCode(2);
    }

    public function test_an_unknown_document_is_an_invalid_call(): void
    {
        $this->command('openapi:inspect', ['route' => 'ping', '--document' => 'v9'])->assertExitCode(2);
    }

    private function variants(string|false $head): void
    {
        $config = $this->app?->make(Repository::class);
        self::assertInstanceOf(Repository::class, $config);
        $config->set('openapi.documents.v1.variants', ['values' => ApiRevision::class, 'head' => $head]);
    }
}
