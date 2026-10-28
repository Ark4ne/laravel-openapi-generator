<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature\Laravel;

use Ark4ne\OpenApi\Laravel\Commands\InspectCommand;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\ResponseController;
use Ark4ne\OpenApi\Tests\Fixtures\Types\UntypedController;
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

    public function test_a_route_out_of_the_document_is_reported(): void
    {
        $this->command('openapi:inspect', ['route' => 'home'])
            ->expectsOutputToContain('home is not in the document v1')
            ->assertExitCode(1);
    }

    public function test_an_unknown_document_is_an_invalid_call(): void
    {
        $this->command('openapi:inspect', ['route' => 'ping', '--document' => 'v9'])->assertExitCode(2);
    }
}
