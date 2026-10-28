<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature\Security;

use Ark4ne\OpenApi\Configuration\SamplingConfiguration;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Extraction\OperationContextFactory;
use Ark4ne\OpenApi\Extraction\Operations\OperationExtractors;
use Ark4ne\OpenApi\Extraction\Requests\RequestExtractors;
use Ark4ne\OpenApi\Extraction\Responses\OperationResponses;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInOperationIdStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInQueryStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInSummaryStrategy;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInTagStrategy;
use Ark4ne\OpenApi\Generation\Passes;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Tests\Fixtures\Requests\RequestController;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\ResponseController;
use Ark4ne\OpenApi\Tests\Fixtures\Routing\Controllers\BindingController;
use Ark4ne\OpenApi\Tests\Unit\Sampling\SamplingTestCase;
use Ark4ne\OpenApi\Types\TypeResolver;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Middleware\ThrottleRequests;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Security and error responses of a small application, from its routes through extraction and passes. The expected
 * documents come with the writing (E8).
 */
#[CoversNothing]
final class SecurityAndErrorsTest extends SamplingTestCase
{
    protected function defineRoutes($router): void
    {
        $router->aliasMiddleware('auth', Authenticate::class);
        $router->aliasMiddleware('throttle', ThrottleRequests::class);
        $router->group(['middleware' => ['auth:sanctum', 'throttle:api']], static function () use ($router): void {
            $router->get('api/articles/{article}', [ResponseController::class, 'show']);
            $router->post('api/users', [RequestController::class, 'store']);
            $router->get('api/users/{user}', [BindingController::class, 'user'])->middleware('can:view,user');
        });
        $router->get('api/public', [ResponseController::class, 'nothing']);
    }

    public function test_each_operation_gets_its_security_and_its_error_responses(): void
    {
        $document = $this->document();
        $summary = [];

        foreach ($document->operations as $operation) {
            $summary[(string) $operation->route] = [
                'security' => array_map(static fn($requirement): array => array_keys($requirement->schemes), $operation->security ?? []),
                'responses' => array_map(static fn(ResponseDescription $response): string => $response->status . ($response->reference === null ? '' : " {$response->reference}"), $operation->responses),
            ];
        }

        self::assertSame([
            'GET /api/articles/{article}' => ['security' => [['sanctum'], ['session', 'xsrf']], 'responses' => ['200', '401 Unauthenticated', '429 TooManyRequests']],
            'POST /api/users' => ['security' => [['sanctum'], ['session', 'xsrf']], 'responses' => ['200', '401 Unauthenticated', '403 Forbidden', '422 ValidationFailed', '429 TooManyRequests']],
            'GET /api/users/{user}' => ['security' => [['sanctum'], ['session', 'xsrf']], 'responses' => ['200', '401 Unauthenticated', '403 Forbidden', '404 NotFound', '429 TooManyRequests']],
            'GET /api/public' => ['security' => [], 'responses' => ['200']],
        ], $summary);
        self::assertSame(['sanctum', 'session', 'xsrf'], array_keys($document->securitySchemes));
        self::assertSame(['Unauthenticated', 'Forbidden', 'NotFound', 'ValidationFailed', 'TooManyRequests'], array_keys($document->responses));
        self::assertSame(['Article', 'Author', 'Error', 'ValidationError'], array_keys($document->schemas));
    }

    public function test_the_operation_ids_are_unique_and_the_path_parameters_go_up(): void
    {
        $document = $this->document();

        self::assertSame(['getApiArticlesByArticle', 'postApiUsers', 'getApiUsersByUser', 'getApiPublic'], array_map(static fn(OperationDescription $operation): ?string => $operation->operationId, $document->operations));
        self::assertSame(['/api/articles/{article}', '/api/users/{user}'], array_keys($document->pathParameters));
    }

    private function document(): DocumentDescription
    {
        $context = $this->context(sampling: new SamplingConfiguration(examples: false));
        $types = new TypeResolver();
        $session = $this->samplingSession();
        $container = $this->app?->make(Container::class);
        self::assertInstanceOf(Container::class, $container);
        $factory = new OperationContextFactory($types);
        $operations = OperationExtractors::default(new BuiltInSummaryStrategy(), new BuiltInTagStrategy(), new BuiltInOperationIdStrategy('method_path'));
        $requests = RequestExtractors::default($container, new BuiltInQueryStrategy());
        $responses = OperationResponses::default(Samplers::default($session, $types), $types);
        $definitions = $this->definitions($context);

        $document = $session->run(static function () use ($definitions, $factory, $operations, $requests, $responses, $context): DocumentDescription {
            $document = DocumentDescription::create();

            foreach ($definitions as $definition) {
                $operation = $factory->create($definition, $context);
                $description = $operations->extract($operation, $operation->describe(), $context);
                $description = $description->request($requests->extract($operation, $description->request, $context));
                $document = $document->addOperations($description->responses(...$responses->describe($operation, $context)));
            }

            return $document;
        }, $context);

        return Passes::default()->process($document, $context);
    }

}
