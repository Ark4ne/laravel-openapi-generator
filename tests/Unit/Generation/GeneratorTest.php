<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Generation;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Generation\GeneratedDocument;
use Ark4ne\OpenApi\Generation\Generator;
use Ark4ne\OpenApi\Tests\Feature\DocumentScenario;
use Ark4ne\OpenApi\Tests\Fixtures\Metadata\Controllers\UserController;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\ResponseController;
use Ark4ne\OpenApi\Tests\Fixtures\Types\BrokenWebhooks;
use Ark4ne\OpenApi\Tests\Fixtures\Webhooks\OrderController;
use Ark4ne\OpenApi\Tests\Fixtures\Webhooks\OrderWebhooks;
use Ark4ne\OpenApi\Tests\Fixtures\Webhooks\PaymentWebhooks;
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

    public function test_a_webhook_is_filed_apart_and_a_webhook_without_name_is_left_out(): void
    {
        $this->router()->post('hooks/orders/created', [OrderWebhooks::class, 'created']);
        $this->router()->post('hooks/payments/failed', [PaymentWebhooks::class, 'failed']);
        $this->router()->post('api/orders', [OrderController::class, 'store']);

        $description = $this->generator()->generate($this->configuration(), 'v1')->description;

        self::assertSame(['/api/orders'], array_map(static fn(OperationDescription $o): string => $o->path, $description->operations));
        self::assertSame(['order.created'], array_map(static fn(OperationDescription $o): ?string => $o->webhook, $description->webhooks));
        self::assertCount(4, $description->operations[0]->callbacks);
    }

    public function test_the_webhook_attributes_are_read_once(): void
    {
        $this->router()->post('hooks/broken', [BrokenWebhooks::class, 'sent']);

        $generated = $this->generator()->generate($this->configuration(), 'v1');
        $invalid = array_values(array_filter(array_map(strval(...), $generated->context->diagnostics->all()), static fn(string $d): bool => str_contains($d, 'invalid #[')));

        self::assertCount(2, $invalid);
        self::assertSame(['broken.sent'], array_map(static fn(OperationDescription $o): ?string => $o->operationId, $generated->description->webhooks));
    }

    private function configuration(): Configuration
    {
        return Configuration::fromArray(['documents' => ['v1' => ['info' => ['title' => 'API', 'version' => '1.0.0'], 'routes' => ['include' => null]]], 'sampling' => ['examples' => false]]);
    }
}
