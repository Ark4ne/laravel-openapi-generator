<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Generation;

use Ark4ne\OpenApi\Description\CallbackDescription;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Description\RequestDescription;
use Ark4ne\OpenApi\Description\ServerDescription;
use Ark4ne\OpenApi\Generation\Passes\CallbackResolutionPass;
use Ark4ne\OpenApi\Generation\Passes\WebhookPass;
use Ark4ne\OpenApi\Tests\Support\Descriptions;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(WebhookPass::class)]
#[CoversClass(CallbackResolutionPass::class)]
final class WebhookPassesTest extends TestCase
{
    public function test_a_webhook_leaves_out_the_path_parameters_and_the_servers_of_its_route(): void
    {
        $webhook = self::webhook('order.created')
            ->request(RequestDescription::create()->parameters(ParameterDescription::path('order'), ParameterDescription::header('X-Signature')))
            ->servers(ServerDescription::create('https://{account}.example.com'));

        $document = (new WebhookPass())->process(DocumentDescription::create()->webhooks($webhook), $this->context());

        self::assertSame(['X-Signature'], array_map(static fn(ParameterDescription $p): string => $p->name, $document->webhooks[0]->request->parameters));
        self::assertSame([], $document->webhooks[0]->servers);
    }

    public function test_a_webhook_name_given_twice_to_one_method_keeps_its_first_operation(): void
    {
        $context = $this->context();

        $document = (new WebhookPass())->process(DocumentDescription::create()->webhooks(
            self::webhook('order.created')->summary('First'),
            self::webhook('order.created', HttpMethod::Put),
            self::webhook('order.created')->summary('Second')->route('POST /hooks/second'),
        ), $context);

        self::assertSame(['First', null], array_map(static fn(OperationDescription $o): ?string => $o->summary, $document->webhooks));
        self::assertDiagnostics(['[warning] pass POST /hooks/second: the webhook order.created is already sent with this method by POST /hooks/order.created, operation left out'], $context);
    }

    public function test_a_callback_reusing_a_webhook_gets_its_requests_without_operation_id(): void
    {
        $operation = Descriptions::operation(HttpMethod::Post, '/api/orders')->callbacks(CallbackDescription::fromWebhook('orderCreated', '{$request.body#/callbackUrl}', 'order.created'));
        $document = DocumentDescription::create()->operations($operation)->webhooks(
            self::webhook('order.created')->operationId('orderCreated')->summary('Created'),
            self::webhook('order.created', HttpMethod::Put),
            self::webhook('order.updated'),
        );

        $callbacks = (new CallbackResolutionPass())->process($document, $this->context())->operations[0]->callbacks;

        self::assertSame(['post', 'put'], array_map(static fn(CallbackDescription $c): string => $c->operation?->method instanceof HttpMethod ? $c->operation->method->value : '', $callbacks));
        self::assertSame(['{$request.body#/callbackUrl}', '{$request.body#/callbackUrl}'], array_map(static fn(CallbackDescription $c): ?string => $c->operation?->path, $callbacks));
        $resolved = $callbacks[0]->operation;
        self::assertNotNull($resolved);
        self::assertNull($resolved->operationId);
        self::assertNull($resolved->webhook);
        self::assertSame('Created', $resolved->summary);
    }

    public function test_a_callback_reusing_a_webhook_gets_its_callbacks_resolved(): void
    {
        $operation = Descriptions::operation(HttpMethod::Post, '/api/orders')->callbacks(CallbackDescription::fromWebhook('created', '{$request.body#/url}', 'order.created'));
        $document = DocumentDescription::create()->operations($operation)->webhooks(
            self::webhook('order.created')->callbacks(CallbackDescription::fromWebhook('shipped', '{$request.body#/next}', 'order.shipped')),
            self::webhook('order.shipped')->summary('Shipped'),
        );

        $nested = (new CallbackResolutionPass())->process($document, $this->context())->operations[0]->callbacks[0]->operation?->callbacks[0];

        self::assertSame('Shipped', $nested?->operation?->summary);
        self::assertSame('{$request.body#/next}', $nested->operation->path);
    }

    public function test_a_webhook_given_twice_without_route_is_reported_without_it(): void
    {
        $context = $this->context();

        (new WebhookPass())->process(DocumentDescription::create()->webhooks(self::webhook('order.created')->route(null), self::webhook('order.created')->route(null)), $context);

        self::assertDiagnostics(['[warning] pass: the webhook order.created is already sent with this method, operation left out'], $context);
    }

    public function test_a_callback_reusing_a_missing_webhook_is_left_out_with_a_warning(): void
    {
        $context = $this->context();
        $operation = Descriptions::operation(HttpMethod::Post, '/api/orders')->callbacks(CallbackDescription::fromWebhook('orderCreated', '{$request.body#/url}', 'order.created'));

        $document = (new CallbackResolutionPass())->process(Descriptions::document($operation), $context);

        self::assertSame([], $document->operations[0]->callbacks);
        self::assertDiagnostics(['[warning] pass POST /api/orders: the callback orderCreated reuses the webhook order.created, which does not exist: callback left out'], $context);
    }

    public function test_a_callback_name_expression_and_method_given_twice_keeps_the_first_request(): void
    {
        $context = $this->context();
        $sent = static fn(HttpMethod $method, string $summary): OperationDescription => OperationDescription::create($method, '{$request.body#/url}')->summary($summary);
        $operation = Descriptions::operation(HttpMethod::Post, '/api/orders')->callbacks(
            CallbackDescription::create('shipped', '{$request.body#/url}', $sent(HttpMethod::Post, 'First')),
            CallbackDescription::create('shipped', '{$request.body#/url}', $sent(HttpMethod::Put, 'Put')),
            CallbackDescription::create('shipped', '{$request.body#/url}', $sent(HttpMethod::Post, 'Second')),
            CallbackDescription::create('delivered', '{$request.body#/url}', $sent(HttpMethod::Post, 'Other name')),
        );

        $callbacks = (new CallbackResolutionPass())->process(Descriptions::document($operation), $context)->operations[0]->callbacks;

        self::assertSame(['First', 'Put', 'Other name'], array_map(static fn(CallbackDescription $c): ?string => $c->operation?->summary, $callbacks));
        self::assertDiagnostics(['[warning] pass POST /api/orders: the callback shipped POST {$request.body#/url} is declared twice, first one kept'], $context);
    }

    private static function webhook(string $name, HttpMethod $method = HttpMethod::Post): OperationDescription
    {
        return Descriptions::operation($method, "/hooks/{$name}")->webhook($name);
    }
}
