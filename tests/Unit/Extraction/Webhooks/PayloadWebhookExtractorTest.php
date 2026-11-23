<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Extraction\Webhooks;

use Ark4ne\OpenApi\Attributes\Operation;
use Ark4ne\OpenApi\Configuration\SamplingConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Extraction\Operations\TranslationOperationExtractor;
use Ark4ne\OpenApi\Extraction\Responses\OperationResponses;
use Ark4ne\OpenApi\Extraction\Webhooks\PayloadWebhookExtractor;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks\ArticleArchived;
use Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks\ArticlePublished;
use Ark4ne\OpenApi\Tests\Fixtures\PayloadWebhooks\Unnamed;
use Ark4ne\OpenApi\Tests\Fixtures\Webhooks\Payloads\BlankName;
use Ark4ne\OpenApi\Tests\Fixtures\Webhooks\Payloads\TranslatedPayload;
use Ark4ne\OpenApi\Tests\Unit\Sampling\SamplingTestCase;
use Ark4ne\OpenApi\Types\TypeResolver;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Resources\Json\JsonResource;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PayloadWebhookExtractor::class)]
#[CoversClass(TranslationOperationExtractor::class)]
final class PayloadWebhookExtractorTest extends SamplingTestCase
{
    public function test_a_payload_class_is_a_post_webhook_tagged_webhooks_with_its_resource_as_required_body(): void
    {
        $context = $this->payloadContext();
        $webhook = $this->extractor()->extract(ArticlePublished::class, $context);

        self::assertNotNull($webhook);
        self::assertSame(HttpMethod::Post, $webhook->method);
        self::assertSame('article.published', $webhook->webhook);
        self::assertSame('article.published', $webhook->operationId);
        self::assertSame('POST article.published', $webhook->route);
        self::assertSame(['Webhooks'], $webhook->tags);
        self::assertSame('An article was published.', $webhook->summary);
        self::assertSame('Sent once, when the article goes live.', $webhook->description);
        self::assertTrue($webhook->request->body?->required);
        self::assertSame(['application/json'], array_keys($webhook->request->body->contents));
        self::assertSame('#/components/schemas/ArticlePublished', $webhook->request->body->contents['application/json']->ref);
        self::assertSame(['X-Signature', 'X-Attempt'], array_map(static fn($parameter): string => $parameter->name, $webhook->request->parameters));
        self::assertTrue($webhook->request->parameters[0]->required);
        self::assertFalse($webhook->request->parameters[1]->required);
        self::assertSame([200], array_map(static fn($response): int|string => $response->status, $webhook->responses));
        self::assertSame('OK', $webhook->responses[0]->description);
        self::assertSame([], $context->diagnostics->all());
    }

    public function test_the_method_operation_and_responses_of_the_class_are_read(): void
    {
        $webhook = $this->extractor()->extract(ArticleArchived::class, $this->payloadContext());

        self::assertNotNull($webhook);
        self::assertSame(HttpMethod::Put, $webhook->method);
        self::assertSame('articleArchived', $webhook->operationId);
        self::assertSame('An article was archived', $webhook->summary);
        self::assertSame(['Articles'], $webhook->tags);
        self::assertTrue($webhook->deprecated);
        self::assertSame([204, 410], array_map(static fn($response): int|string => $response->status, $webhook->responses));
    }

    public function test_a_class_without_name_or_without_webhook_gives_no_webhook(): void
    {
        $context = $this->payloadContext();

        self::assertNull($this->extractor()->extract(Unnamed::class, $context));
        self::assertNull($this->extractor()->extract(BlankName::class, $context));
        self::assertNull($this->extractor()->extract(JsonResource::class, $context));
        self::assertSame([
            '[warning] operation ' . Unnamed::class . ': #[Webhook] without name on the payload class ' . Unnamed::class . ', webhook left out',
            '[warning] operation ' . BlankName::class . ': #[Webhook] without name on the payload class ' . BlankName::class . ', webhook left out',
        ], array_map(strval(...), $context->diagnostics->all()));
    }

    public function test_a_tag_replaces_the_default_tag_an_invalid_attribute_is_reported_and_texts_are_translated(): void
    {
        $translator = $this->app?->make(Translator::class);
        self::assertInstanceOf(Translator::class, $translator);
        $translator->addLines(['docs.webhooks.author' => 'Auteur modifié'], 'fr');
        $context = $this->payloadContext('fr');

        $webhook = $this->extractor(new TranslationOperationExtractor($translator))->extract(TranslatedPayload::class, $context);

        self::assertNotNull($webhook);
        self::assertSame(HttpMethod::Patch, $webhook->method);
        self::assertSame('Auteur modifié', $webhook->summary);
        self::assertSame(['Audit'], $webhook->tags);
        self::assertSame('Audit', $webhook->declaredTags[0]->name);
        // The second `X-Request-Id` (another case) completes the first; an example without type keeps the string.
        self::assertSame(['X-Request-Id', 'X-Trace'], array_map(static fn($parameter): string => $parameter->name, $webhook->request->parameters));
        self::assertSame('Correlation id', $webhook->request->parameters[0]->description);
        self::assertTrue($webhook->request->parameters[0]->required);
        self::assertSame(['type' => 'string', 'minLength' => 1, 'example' => 'f3a1'], $webhook->request->parameters[0]->schema->toArray());
        self::assertSame(['type' => 'string', 'example' => 'abc'], $webhook->request->parameters[1]->schema->toArray());
        self::assertFalse($webhook->request->parameters[1]->required);
        self::assertCount(1, $context->diagnostics->all());
        self::assertStringStartsWith('[warning] operation PATCH author.updated: invalid #[' . Operation::class . '] on ' . TranslatedPayload::class . ', attribute ignored: ', (string) $context->diagnostics->all()[0]);
    }

    private function extractor(?TranslationOperationExtractor $translation = null): PayloadWebhookExtractor
    {
        $types = new TypeResolver();
        $responses = OperationResponses::default(Samplers::default($this->samplingSession(), $types), $types);

        return new PayloadWebhookExtractor($responses->resources(), $responses->declared(), $types, $translation);
    }

    private function payloadContext(?string $locale = null): GenerationContext
    {
        return $this->context(locale: $locale, sampling: new SamplingConfiguration(examples: false));
    }
}
