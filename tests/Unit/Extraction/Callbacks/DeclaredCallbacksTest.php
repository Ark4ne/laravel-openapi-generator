<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Extraction\Callbacks;

use Ark4ne\OpenApi\Attributes\Callback;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\BodyDescription;
use Ark4ne\OpenApi\Description\CallbackDescription;
use Ark4ne\OpenApi\Extraction\Callbacks\DeclaredCallbacks;
use Ark4ne\OpenApi\Extraction\Responses\OperationResponses;
use Ark4ne\OpenApi\Extraction\Responses\ResourceFormat;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Tests\Fixtures\Integrations\TestResourceFormat;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Http\Resources\ArticleResource;
use Ark4ne\OpenApi\Tests\Fixtures\Types\BrokenWebhooks;
use Ark4ne\OpenApi\Tests\Fixtures\Webhooks\OrderController;
use Ark4ne\OpenApi\Tests\Fixtures\Webhooks\OrderPayload;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Tests\Unit\Sampling\SamplingTestCase;
use Ark4ne\OpenApi\Types\TypeResolver;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DeclaredCallbacks::class)]
final class DeclaredCallbacksTest extends SamplingTestCase
{
    public function test_each_callback_describes_its_request_and_the_expected_response(): void
    {
        $callbacks = $this->callbacks('store');

        self::assertSame(['orderCreated', 'orderShipped', 'orderShipped', 'article'], array_map(static fn(CallbackDescription $c): string => $c->name, $callbacks));
        self::assertSame('order.created', $callbacks[0]->webhook);
        self::assertNull($callbacks[0]->operation);

        $shipped = $callbacks[1]->operation;
        self::assertNotNull($shipped);
        self::assertSame(HttpMethod::Post, $shipped->method);
        self::assertSame('{$request.body#/callbackUrl}', $shipped->path);
        self::assertSame('The order was shipped', $shipped->description);
        self::assertInstanceOf(BodyDescription::class, $shipped->request->body);
        self::assertTrue($shipped->request->body->required);
        Schemas::assertSame(['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'carrier' => ['type' => 'string']], 'required' => ['id', 'carrier'], 'additionalProperties' => false], $shipped->request->body->contents['application/json']);
        self::assertSame([[200, 'OK']], array_map(static fn($r): array => [$r->status, $r->description], $shipped->responses));

        self::assertSame(HttpMethod::Put, $callbacks[2]->operation?->method);
        self::assertSame(204, $callbacks[2]->operation->responses[0]->status);

        $article = $callbacks[3]->operation?->request->body;
        self::assertInstanceOf(BodyDescription::class, $article);
        self::assertSame(['data', 'meta'], array_keys((array) $article->contents['application/json']->properties));
    }

    public function test_a_callback_with_several_sources_is_ignored_with_a_warning(): void
    {
        $context = $this->context();

        $callbacks = $this->callbacks('update', $context);

        self::assertSame(['missing'], array_map(static fn(CallbackDescription $c): string => $c->name, $callbacks));
        self::assertStringEndsWith('::update, attribute ignored: webhook, resource and type are mutually exclusive', (string) $context->diagnostics->all()[0]);
        self::assertStringContainsString('invalid #[' . Callback::class . ']', (string) $context->diagnostics->all()[0]);
    }

    public function test_a_resource_the_generator_does_not_know_is_reported(): void
    {
        $context = $this->context();

        $callbacks = $this->callbacks('dto', $context, BrokenWebhooks::class);

        self::assertNull($callbacks[0]->operation?->request->body);
        self::assertDiagnostics(['[warning] response POST /api/orders: #[Callback] dto: ' . OrderPayload::class . ' is not a resource, request written without body'], $context);
    }

    public function test_a_resource_of_a_format_is_sent_in_its_media_type(): void
    {
        $callbacks = $this->callbacks('store', formats: [new TestResourceFormat('application/x-test', [ArticleResource::class])]);
        $article = array_values(array_filter($callbacks, static fn(CallbackDescription $callback): bool => $callback->name === 'article'))[0] ?? null;

        self::assertSame(['application/x-test'], array_keys($article?->operation?->request->body->contents ?? []));
    }

    /**
     * @param class-string $controller
     * @param list<ResourceFormat> $formats
     *
     * @return list<CallbackDescription>
     */
    private function callbacks(string $action, ?GenerationContext $context = null, string $controller = OrderController::class, array $formats = []): array
    {
        $this->router()->post('api/orders', [$controller, $action]);
        $context ??= $this->context();
        $types = new TypeResolver();
        $responses = OperationResponses::default(Samplers::default($this->samplingSession(), $types), $types, $formats);

        return $this->samplingSession()->run(fn(): array => (new DeclaredCallbacks($responses->declared()))->describe($this->operation($context), $context), $context);
    }
}
