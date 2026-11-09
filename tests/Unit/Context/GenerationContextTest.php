<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Context;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Configuration\InfoConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Tests\TestCase;
use Closure;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(GenerationContext::class)]
final class GenerationContextTest extends TestCase
{
    public function test_the_context_carries_the_locale_of_the_generation(): void
    {
        self::assertSame('fr', $this->preparing(locale: 'fr')->locale);
    }

    public function test_a_sample_request_is_a_plain_get_request_without_preparer(): void
    {
        $request = $this->preparing()->sampleRequest();

        self::assertSame('GET', $request->getMethod());
        self::assertSame('/', $request->getPathInfo());
    }

    public function test_the_preparers_prepare_every_sample_request_in_order(): void
    {
        $context = $this->preparing(preparers: [
            static fn(Request $request, GenerationContext $context) => $request->attributes->set('prepared', [$context->document->name]),
            static fn(Request $request) => $request->attributes->set('prepared', [...(array) $request->attributes->get('prepared'), 'second']),
        ]);

        self::assertSame(['v1', 'second'], $context->sampleRequest()->attributes->get('prepared'));
    }

    public function test_a_request_of_the_generator_is_prepared_as_a_sample_request(): void
    {
        $context = $this->preparing(preparers: [
            static fn(Request $request) => $request->attributes->set('prepared', true),
        ]);
        $request = Request::create('/api/users/1', 'PUT');

        self::assertSame($request, $context->prepareRequest($request));
        self::assertTrue($request->attributes->get('prepared'));
        self::assertSame('PUT', $request->getMethod());
    }

    public function test_a_failing_preparer_is_reported_and_the_next_ones_run(): void
    {
        $context = $this->preparing(preparers: [
            static fn() => throw new RuntimeException('no revision'),
            static fn(Request $request) => $request->attributes->set('prepared', true),
        ]);

        $request = $context->sampleRequest();

        self::assertTrue($request->attributes->get('prepared'));
        self::assertDiagnostics(['[warning] sampling: cannot prepare a sample request: no revision'], $context);
    }

    public function test_the_preparers_run_once_for_every_sample_request_of_a_context(): void
    {
        $runs = 0;
        $context = $this->preparing(preparers: [
            static function (Request $request) use (&$runs): void {
                $runs++;
                $request->attributes->set('prepared', true);
            },
        ]);

        $requests = [$context->sampleRequest(), $context->sampleRequest(), $context->sampleRequest()];

        self::assertSame(1, $runs);

        foreach ($requests as $request) {
            self::assertTrue($request->attributes->get('prepared'));
        }
    }

    public function test_a_failing_preparer_is_reported_once_for_every_sample_request_of_a_context(): void
    {
        $context = $this->preparing(preparers: [static fn() => throw new RuntimeException('no revision')]);

        $context->sampleRequest();
        $context->sampleRequest();

        self::assertDiagnostics(['[warning] sampling: cannot prepare a sample request: no revision'], $context);
    }

    public function test_a_failing_preparer_of_a_request_is_reported_at_its_stage_and_route(): void
    {
        $context = $this->preparing(preparers: [static fn() => throw new RuntimeException('no revision')]);

        $context->prepareRequest(Request::create('/api/users', 'POST'), Stage::Request, 'POST /api/users');

        self::assertDiagnostics(['[warning] request POST /api/users: cannot prepare the request Illuminate\Http\Request: no revision'], $context);
    }

    public function test_a_failing_preparer_of_an_integration_is_named(): void
    {
        $context = $this->preparing(preparers: ['json_api' => static fn() => throw new RuntimeException('no revision')]);

        $context->sampleRequest();

        self::assertDiagnostics(['[warning] sampling: cannot prepare a sample request for the integration json_api: no revision'], $context);
    }

    public function test_every_sample_request_is_a_new_request(): void
    {
        $context = $this->preparing();
        $first = $context->sampleRequest();
        $first->attributes->set('changed', true);

        self::assertNotSame($first, $context->sampleRequest());
        self::assertFalse($context->sampleRequest()->attributes->has('changed'));
    }

    /**
     * @param array<array-key, Closure(Request, GenerationContext): void> $preparers
     */
    private function preparing(?string $locale = null, array $preparers = []): GenerationContext
    {
        $document = new DocumentConfiguration('v1', new InfoConfiguration('API', '1'));

        return GenerationContext::for(new Configuration(['v1' => $document]), $document, $locale, $preparers);
    }
}
