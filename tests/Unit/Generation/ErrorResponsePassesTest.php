<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Generation;

use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\HeaderDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Description\RequestDescription;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Description\ResponseOrigin;
use Ark4ne\OpenApi\Description\SecurityDescription;
use Ark4ne\OpenApi\Generation\LaravelErrorBodies;
use Ark4ne\OpenApi\Generation\Pass;
use Ark4ne\OpenApi\Generation\Passes\AuthorizationResponsePass;
use Ark4ne\OpenApi\Generation\Passes\NotFoundResponsePass;
use Ark4ne\OpenApi\Generation\Passes\ThrottleResponsePass;
use Ark4ne\OpenApi\Generation\Passes\ThrownExceptionResponsePass;
use Ark4ne\OpenApi\Generation\Passes\ValidationErrorResponsePass;
use Ark4ne\OpenApi\Tests\Fixtures\Integrations\TestErrorBodies;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Models\User;
use Ark4ne\OpenApi\Tests\Support\Descriptions;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApiWriter\Enums\HttpMethod;
use Ark4ne\OpenApiWriter\Objects\Schema;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ThrownExceptionResponsePass::class)]
#[CoversClass(ValidationErrorResponsePass::class)]
#[CoversClass(AuthorizationResponsePass::class)]
#[CoversClass(NotFoundResponsePass::class)]
#[CoversClass(ThrottleResponsePass::class)]
#[CoversClass(LaravelErrorBodies::class)]
final class ErrorResponsePassesTest extends TestCase
{
    public function test_responses_of_one_status_are_merged_with_their_causes(): void
    {
        $operation = Descriptions::operation()->addResponses(
            ResponseDescription::create(404)->description('Not Found (ModelNotFoundException)')->content('application/json', Schema::ref('Error'))->origin(ResponseOrigin::Exception),
            ResponseDescription::create(404)->description('Not Found (RecordsNotFoundException)')->content('application/json', Schema::ref('Error'))->origin(ResponseOrigin::Exception),
            ResponseDescription::create(409)->description('Conflict (A)')->content('application/json', Schema::ref('Error'))->origin(ResponseOrigin::Exception),
            ResponseDescription::create(409)->description('Seat taken (B)')->content('application/json', Schema::ref('Seat'))->origin(ResponseOrigin::Exception),
        );

        $responses = $this->process(new ThrownExceptionResponsePass(), $operation)->operations[0]->responses;

        self::assertSame([200, 404, 409], array_map(static fn(ResponseDescription $r): int|string => $r->status, $responses));
        self::assertSame("Not Found (ModelNotFoundException)\nNot Found (RecordsNotFoundException)", $responses[1]->description);
        Schemas::assertSame(['$ref' => '#/components/schemas/Error'], $responses[1]->contents['application/json'] ?? Schema::create());
        Schemas::assertSame(['oneOf' => [['$ref' => '#/components/schemas/Error'], ['$ref' => '#/components/schemas/Seat']]], $responses[2]->contents['application/json'] ?? Schema::create());
    }

    public function test_a_declared_response_wins_over_the_exceptions_of_its_status(): void
    {
        $operation = Descriptions::operation()->addResponses(
            ResponseDescription::create(404)->description('Not Found (ModelNotFoundException)')->origin(ResponseOrigin::Exception),
            ResponseDescription::create(404)->description('No such user')->origin(ResponseOrigin::Declared),
        );

        $responses = $this->process(new ThrownExceptionResponsePass(), $operation)->operations[0]->responses;

        self::assertSame(['OK', 'No such user'], array_map(static fn(ResponseDescription $r): ?string => $r->description, $responses));
    }

    public function test_validated_requests_answer_422_with_the_shared_response(): void
    {
        $operation = Descriptions::operation(HttpMethod::Post)->request(RequestDescription::create()->validated());

        $document = $this->process(new ValidationErrorResponsePass(new LaravelErrorBodies()), $operation, Descriptions::operation(HttpMethod::Get, '/api/other'));

        self::assertSame('ValidationFailed', $document->operations[0]->responses[1]->reference);
        self::assertSame(422, $document->operations[0]->responses[1]->status);
        self::assertCount(1, $document->operations[1]->responses);
        self::assertSame('Validation failed', $document->responses['ValidationFailed']->description);
        Schemas::assertSame(['$ref' => '#/components/schemas/ValidationError'], $document->responses['ValidationFailed']->contents['application/json'] ?? Schema::create());
    }

    public function test_an_existing_status_is_not_replaced(): void
    {
        $operation = Descriptions::operation(HttpMethod::Post)
            ->request(RequestDescription::create()->validated())
            ->addResponses(ResponseDescription::create(422)->description('Mine'));

        $responses = $this->process(new ValidationErrorResponsePass(new LaravelErrorBodies()), $operation)->operations[0]->responses;

        self::assertSame(['OK', 'Mine'], array_map(static fn(ResponseDescription $r): ?string => $r->description, $responses));
    }

    public function test_authenticated_operations_answer_401_and_authorized_ones_403(): void
    {
        $document = $this->process(
            new AuthorizationResponsePass(new LaravelErrorBodies()),
            Descriptions::operation()->security(SecurityDescription::create(['sanctum' => []])),
            Descriptions::operation(HttpMethod::Put, '/api/users/{user}', 'can:update,user'),
            Descriptions::operation(HttpMethod::Post, '/api/users')->request(RequestDescription::create()->authorized()),
            Descriptions::operation(HttpMethod::Get, '/api/public')->security(SecurityDescription::create()),
        );

        self::assertSame([[200, 401], [200, 403], [200, 403], [200]], array_map(static fn(OperationDescription $o): array => array_map(static fn(ResponseDescription $r): int|string => $r->status, $o->responses), $document->operations));
        self::assertSame(['Unauthenticated', 'Forbidden'], array_keys($document->responses));
    }

    public function test_bound_models_answer_404(): void
    {
        $operation = Descriptions::operation(HttpMethod::Get, '/api/users/{user}')->request(RequestDescription::create()->parameters(ParameterDescription::path('user')->boundTo(User::class)));

        $document = $this->process(new NotFoundResponsePass(new LaravelErrorBodies()), $operation, Descriptions::operation(HttpMethod::Get, '/api/users/{id}')->request(RequestDescription::create()->parameters(ParameterDescription::path('id'))));

        self::assertSame('NotFound', $document->operations[0]->responses[1]->reference);
        self::assertCount(1, $document->operations[1]->responses);
    }

    public function test_throttled_operations_answer_429_with_the_rate_limit_headers(): void
    {
        $document = $this->process(new ThrottleResponsePass(new LaravelErrorBodies()), Descriptions::operation(HttpMethod::Get, '/api/users', 'throttle:60,1'));

        self::assertSame('TooManyRequests', $document->operations[0]->responses[1]->reference);
        self::assertSame(['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'Retry-After', 'X-RateLimit-Reset'], array_map(static fn(HeaderDescription $h): string => $h->name, $document->responses['TooManyRequests']->headers));
    }

    public function test_the_shared_error_responses_take_the_media_type_of_the_error_bodies(): void
    {
        $errors = new TestErrorBodies();
        $operation = Descriptions::operation(HttpMethod::Get, '/api/users/{user}')->request(RequestDescription::create()->parameters(ParameterDescription::path('user')->boundTo(User::class)));

        $document = $this->process(new NotFoundResponsePass($errors), $operation);

        self::assertSame(['application/problem+json'], array_keys($document->responses['NotFound']->contents));
        self::assertSame('application/json', (new LaravelErrorBodies())->mediaType());
    }

    private function process(Pass $pass, OperationDescription ...$operations): DocumentDescription
    {
        return $pass->process(Descriptions::document(...$operations), $this->context());
    }
}
