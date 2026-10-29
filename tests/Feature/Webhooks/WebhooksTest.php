<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature\Webhooks;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Tests\Feature\DocumentScenario;
use Ark4ne\OpenApi\Tests\Fixtures\Webhooks\OrderController;
use Ark4ne\OpenApi\Tests\Fixtures\Webhooks\OrderWebhooks;
use Ark4ne\OpenApi\Tests\Fixtures\Webhooks\PaymentWebhooks;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApiWriter\Enums\VendorExtensions;
use Ark4ne\OpenApiWriter\Enums\Version;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The requests an application sends: webhooks on routes carrying `#[Webhook]`, callbacks of an operation.
 */
#[CoversNothing]
final class WebhooksTest extends DocumentScenario
{
    protected function defineRoutes($router): void
    {
        $router->post('hooks/orders/created', [OrderWebhooks::class, 'created']);
        $router->post('hooks/orders/{order}/cancel', [OrderWebhooks::class, 'cancel']);
        $router->post('hooks/payments/failed', [PaymentWebhooks::class, 'failed'])->name('payment.failed');
        $router->post('hooks/payments/refunded', [PaymentWebhooks::class, 'refunded']);
        $router->post('api/orders', [OrderController::class, 'store'])->name('orders.store');
        $router->put('api/orders', [OrderController::class, 'update'])->name('orders.update');
    }

    public function test_the_documents_are_those_expected(): void
    {
        $this->assertExpectedDocuments(__DIR__);
    }

    public function test_the_webhooks_are_written_as_x_webhooks_in_3_0_with_the_redoc_extensions(): void
    {
        $configuration = Configuration::fromArray([
            'documents' => ['v1' => ['openapi' => '3.0.4', 'vendor_extensions' => 'redoc', 'info' => ['title' => 'API', 'version' => '1'], 'routes' => ['include' => null]]],
            'sampling' => ['examples' => false],
        ]);

        $written = $this->generator()->generate($configuration, 'v1')->openApi->toArray(Version::V3_0_4, VendorExtensions::Redoc);

        self::assertSame(['order.created', 'order.cancelled', 'payment.failed', 'payment.refunded'], array_keys((array) Schemas::at($written, 'x-webhooks')));
        self::assertArrayNotHasKey('webhooks', $written);
    }
}
