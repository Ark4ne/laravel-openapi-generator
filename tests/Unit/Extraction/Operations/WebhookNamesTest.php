<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Extraction\Operations;

use Ark4ne\OpenApi\Extraction\OperationContextFactory;
use Ark4ne\OpenApi\Extraction\Operations\AttributeOperationExtractor;
use Ark4ne\OpenApi\Tests\Fixtures\Webhooks\OrderController;
use Ark4ne\OpenApi\Tests\Fixtures\Webhooks\OrderWebhooks;
use Ark4ne\OpenApi\Tests\Fixtures\Webhooks\PaymentWebhooks;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApi\Types\TypeResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(AttributeOperationExtractor::class)]
final class WebhookNamesTest extends TestCase
{
    /**
     * @return iterable<string, array{array{class-string, string}, string|null, string|null}>
     */
    public static function webhooks(): iterable
    {
        yield 'controller prefix and method name' => [[OrderWebhooks::class, 'created'], null, 'order.created'];
        yield 'controller prefix and action name' => [[OrderWebhooks::class, 'cancel'], 'hooks.cancel', 'order.cancelled'];
        yield 'route name' => [[PaymentWebhooks::class, 'failed'], 'payment.failed', 'payment.failed'];
        yield 'action name over the route name' => [[PaymentWebhooks::class, 'refunded'], 'hooks.refunded', 'payment.refunded'];
        yield 'no attribute' => [[OrderController::class, 'store'], 'orders.store', null];
    }

    /**
     * @param array{class-string, string} $action
     */
    #[DataProvider('webhooks')]
    public function test_the_webhook_is_named_by_its_attributes_then_by_its_route(array $action, ?string $route, ?string $name): void
    {
        $route === null ? $this->router()->post('hooks/action', $action) : $this->router()->post('hooks/action', $action)->name($route);
        $context = $this->context();
        $operation = $this->operation($context);

        self::assertSame($name, (new AttributeOperationExtractor())->extract($operation, $operation->describe(), $context)->webhook);
        self::assertDiagnostics([], $context);
    }

    public function test_the_operation_id_of_a_webhook_is_its_name_unless_an_attribute_gives_one(): void
    {
        $this->router()->post('hooks/created', [OrderWebhooks::class, 'created']);
        $this->router()->post('hooks/refunded', [PaymentWebhooks::class, 'refunded']);
        $context = $this->context();
        $extractor = new AttributeOperationExtractor();
        $factory = new OperationContextFactory(new TypeResolver());
        $ids = [];

        foreach ($this->definitions($context) as $definition) {
            $operation = $factory->create($definition, $context);
            $ids[] = $extractor->extract($operation, $operation->describe()->operationId('postHooks'), $context)->operationId;
        }

        self::assertSame(['orderCreated', 'payment.refunded'], $ids);
    }

    public function test_a_webhook_without_any_name_is_reported(): void
    {
        $this->router()->post('hooks/failed', [PaymentWebhooks::class, 'failed']);
        $context = $this->context();
        $operation = $this->operation($context);

        self::assertNull((new AttributeOperationExtractor())->extract($operation, $operation->describe(), $context)->webhook);
        self::assertDiagnostics(['[warning] operation POST /hooks/failed: #[Webhook] without name on a route without name, route left out'], $context);
    }
}
