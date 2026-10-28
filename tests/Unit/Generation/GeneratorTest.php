<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Generation;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Generation\GeneratedDocument;
use Ark4ne\OpenApi\Generation\Generator;
use Ark4ne\OpenApi\Tests\Feature\DocumentScenario;
use Ark4ne\OpenApi\Tests\Fixtures\Metadata\Controllers\UserController;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\ResponseController;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApiWriter\Enums\Version;
use Illuminate\Contracts\Translation\Translator;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Generator::class)]
#[CoversClass(GeneratedDocument::class)]
final class GeneratorTest extends DocumentScenario
{
    public function test_a_document_is_generated_from_the_routes(): void
    {
        $this->router()->get('api/articles/{article}', [ResponseController::class, 'show'])->name('articles.show');

        $generated = $this->generator()->generate($this->configuration(), 'v1');
        $document = $generated->openApi->toArray(Version::V3_1_2);

        self::assertSame('API', Schemas::at($document, 'info', 'title'));
        self::assertSame(['/api/articles/{article}'], array_keys((array) Schemas::at($document, 'paths')));
        self::assertSame('articles.show', Schemas::at($document, 'paths', '/api/articles/{article}', 'get', 'operationId'));
        self::assertSame(['Article', 'Author'], array_keys((array) Schemas::at($document, 'components', 'schemas')));
        self::assertSame('v1', $generated->context->document->name);
    }

    public function test_two_generations_give_the_same_document(): void
    {
        $this->router()->get('api/articles/{article}', [ResponseController::class, 'show']);
        $this->router()->post('api/articles', [ResponseController::class, 'store']);

        $first = $this->generator()->generate($this->configuration(), 'v1')->openApi->toJson();
        $second = $this->generator()->generate($this->configuration(), 'v1')->openApi->toJson();

        self::assertSame($first, $second);
    }

    public function test_one_document_per_document_and_language(): void
    {
        $translator = $this->app?->make(Translator::class);
        self::assertInstanceOf(Translator::class, $translator);
        $translator->addLines(['docs.users' => 'Utilisateurs'], 'fr');
        $this->router()->get('api/users', [UserController::class, 'index']);
        $configuration = Configuration::fromArray([
            'documents' => ['v1' => ['info' => ['title' => 'API', 'version' => '1'], 'routes' => ['include' => null]], 'v2' => ['info' => ['title' => 'API 2', 'version' => '2'], 'routes' => ['include' => null]]],
            'languages' => ['en', 'fr'],
        ]);

        $generated = $this->generator()->all($configuration);

        self::assertSame(['v1 en', 'v1 fr', 'v2 en', 'v2 fr'], array_map(static fn(GeneratedDocument $document): string => "{$document->context->document->name} {$document->context->locale}", $generated));
    }

    private function configuration(): Configuration
    {
        return Configuration::fromArray(['documents' => ['v1' => ['info' => ['title' => 'API', 'version' => '1.0.0'], 'routes' => ['include' => null]]], 'sampling' => ['examples' => false]]);
    }
}
