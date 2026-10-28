<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature\JsonApi;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Integrations\JsonApi\JsonApiIntegration;
use Ark4ne\OpenApi\Tests\Feature\DocumentScenario;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Controllers\CommentController;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Controllers\PostController;
use Ark4ne\OpenApi\Tests\Fixtures\JsonApi\Controllers\UserController;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApiWriter\Enums\Version;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;

/**
 * An application of `ark4ne/laravel-json-api` resources: documents, collections, `include` and `fields`, JSON:API
 * errors (the port of the v0.6 scenario `openapi-jsonapi.json`).
 */
#[CoversNothing]
#[Group('json-api')]
final class JsonApiTest extends DocumentScenario
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

        Schema::create('comments', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('post_id');
            $table->text('content');
            $table->timestamps();
        });
    }

    protected function defineRoutes($router): void
    {
        $router->get('api/users', [UserController::class, 'index'])->name('users.index');
        $router->get('api/users/{user}', [UserController::class, 'show'])->name('users.show');
        $router->post('api/users', [UserController::class, 'store'])->name('users.store');
        $router->delete('api/users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('auth:sanctum');
        $router->get('api/posts', [PostController::class, 'index'])->name('posts.index');
        $router->get('api/posts/{post}', [PostController::class, 'show'])->name('posts.show');
        $router->get('api/comments/{comment}', [CommentController::class, 'show'])->name('comments.show');
    }

    protected function integrations(): array
    {
        return [new JsonApiIntegration(errors: true)];
    }

    public function test_the_documents_are_those_expected(): void
    {
        $this->assertExpectedDocuments(__DIR__);
    }

    public function test_the_resources_are_json_api_documents_and_the_query_has_include_and_fields(): void
    {
        $written = $this->generator()->generate($this->scenario(), 'v1')->openApi->toArray(Version::V3_1_2);
        $index = ['paths', '/api/users', 'get'];

        self::assertSame(['application/vnd.api+json'], array_keys((array) Schemas::at($written, ...$index, ...['responses', '200', 'content'])));
        self::assertSame(['include', 'fields', 'filter[name]'], array_map(static fn(mixed $parameter): mixed => is_array($parameter) ? $parameter['name'] ?? null : null, (array) Schemas::at($written, ...$index, ...['parameters'])));
        self::assertSame('deepObject', Schemas::at($written, ...$index, ...['parameters', '1', 'style']));
        self::assertSame(['application/vnd.api+json'], array_keys((array) Schemas::at($written, 'components', 'responses', 'ValidationFailed', 'content')));
    }

    private function scenario(): Configuration
    {
        return Configuration::fromArray(['documents' => ['v1' => ['info' => ['title' => 'API', 'version' => '1'], 'routes' => ['include' => null]]], 'sampling' => ['examples' => false]]);
    }
}
