<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature\PayloadWebhooks;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Context\Diagnostic;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use Ark4ne\OpenApi\Tests\Feature\DocumentScenario;
use Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks\ArticleArchived;
use Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks\LegacyWebhooks;
use Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks\SubscriptionController;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Models\Article;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApiWriter\Enums\VendorExtensions;
use Ark4ne\OpenApiWriter\Enums\Version;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Webhooks declared by their payload: `JsonResource` classes marked `#[Webhook]`, found without a route.
 */
#[CoversNothing]
final class PayloadWebhooksTest extends DocumentScenario
{
    private const FIXTURES = __DIR__ . '/../../Fixtures/PayloadWebhooks';

    protected function defineRoutes($router): void
    {
        $router->post('hooks/articles/published', [LegacyWebhooks::class, 'published']);
        $router->post('api/subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
    }

    public function test_the_documents_are_those_expected(): void
    {
        $this->assertExpectedDocuments(__DIR__, ['webhooks' => ['paths' => [self::FIXTURES]]]);
    }

    public function test_a_payload_class_wins_over_a_route_webhook_and_a_class_without_name_is_reported(): void
    {
        $generated = $this->generator()->generate($this->configuration(['paths' => [self::FIXTURES]]), 'v1');
        $messages = array_map(static fn(Diagnostic $diagnostic): string => $diagnostic->message, $generated->context->diagnostics->all());

        self::assertContains('#[Webhook] without name on the payload class Ark4ne\\OpenApi\\Tests\\Fixtures\\PayloadWebhooks\\Unnamed, webhook left out', $messages);
        self::assertContains('the webhook article.published is already sent with this method by POST article.published, operation left out', $messages);
        self::assertSame(['article.archived', 'article.published', 'article.reviewed'], self::names($generated->description->webhooks));
    }

    public function test_the_webhooks_of_a_document_are_chosen_by_their_name_route_webhooks_included(): void
    {
        $included = $this->generator()->generate($this->configuration(['paths' => [self::FIXTURES]], ['webhooks' => ['include' => ['article.p*']]]), 'v1');
        $excluded = $this->generator()->generate($this->configuration(['paths' => [self::FIXTURES]], ['webhooks' => ['exclude' => ['article.published', 'article.re*']]]), 'v1');

        self::assertSame(['article.published'], self::names($included->description->webhooks));
        self::assertSame(['article.archived'], self::names($excluded->description->webhooks));
    }

    public function test_the_payload_webhooks_are_written_as_x_webhooks_in_3_0_with_the_redoc_extensions(): void
    {
        $configuration = $this->configuration(['paths' => [self::FIXTURES]], ['openapi' => '3.0.4', 'vendor_extensions' => 'redoc']);

        $written = $this->generator()->generate($configuration, 'v1')->openApi->toArray(Version::V3_0_4, VendorExtensions::Redoc);

        self::assertSame(['article.archived', 'article.published', 'article.reviewed'], array_keys((array) Schemas::at($written, 'x-webhooks')));
    }

    public function test_a_listed_class_is_documented_without_any_path(): void
    {
        $generated = $this->generator()->generate($this->configuration(['classes' => [ArticleArchived::class]]), 'v1');

        // `article.published` is the route webhook.
        self::assertSame(['article.archived', 'article.published'], self::names($generated->description->webhooks));
    }

    public function test_a_listed_class_that_is_not_a_payload_class_is_an_invalid_configuration(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('openapi.documents.v1.webhooks.classes: expected concrete JsonResource classes marked #[Ark4ne\\OpenApi\\Attributes\\Webhook], got "' . Article::class . '"');

        $this->generator()->prepare($configuration = $this->configuration(['classes' => [Article::class]]), $configuration->document('v1'));
    }

    public function test_a_document_without_payload_webhooks_has_only_its_route_webhooks(): void
    {
        $generated = $this->generator()->generate($this->configuration([]), 'v1');

        self::assertSame(['article.published'], self::names($generated->description->webhooks));
    }

    /**
     * @param list<OperationDescription> $webhooks
     *
     * @return list<string|null>
     */
    private static function names(array $webhooks): array
    {
        $names = array_map(static fn(OperationDescription $webhook): ?string => $webhook->webhook, $webhooks);
        sort($names);

        return $names;
    }

    /**
     * @param array<string, mixed> $webhooks
     * @param array<string, mixed> $document
     */
    private function configuration(array $webhooks, array $document = []): Configuration
    {
        return Configuration::fromArray([
            'documents' => ['v1' => ['info' => ['title' => 'API', 'version' => '1'], 'routes' => ['include' => null], ...$document]],
            'webhooks' => $webhooks,
            'sampling' => ['examples' => false],
        ]);
    }
}
