<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Extraction\Exceptions;

use Ark4ne\OpenApi\Configuration\ExceptionsConfiguration;
use Ark4ne\OpenApi\Extraction\Exceptions\AttributeExceptionMapper;
use Ark4ne\OpenApi\Extraction\Exceptions\ConfiguredExceptionMapper;
use Ark4ne\OpenApi\Extraction\Exceptions\ErrorBodies;
use Ark4ne\OpenApi\Extraction\Exceptions\ExceptionMappers;
use Ark4ne\OpenApi\Extraction\Exceptions\InstantiableHttpExceptionMapper;
use Ark4ne\OpenApi\Extraction\Exceptions\LaravelErrorBodies;
use Ark4ne\OpenApi\Extraction\Exceptions\LaravelExceptionMapper;
use Ark4ne\OpenApi\Extraction\Exceptions\SymfonyHttpExceptionMapper;
use Ark4ne\OpenApi\Extraction\Responses\DeclaredResponses;
use Ark4ne\OpenApi\Extraction\Responses\ResourceCollectionExtractor;
use Ark4ne\OpenApi\Extraction\Responses\ResourceSchemas;
use Ark4ne\OpenApi\Sampling\ModelSchemas;
use Ark4ne\OpenApi\Sampling\NullSamplingSession;
use Ark4ne\OpenApi\Sampling\RelationLoader;
use Ark4ne\OpenApi\Sampling\ResourceModels;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Sampling\SchemaInferrer;
use Ark4ne\OpenApi\Tests\Fixtures\Integrations\TestErrorBodies;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Exceptions\ChildSeatException;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Exceptions\PaymentRequiredException;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Exceptions\SeatTakenException;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Exceptions\TeapotException;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Exceptions\UnknownException;
use Ark4ne\OpenApi\Tests\Support\Schemas;
use Ark4ne\OpenApi\Tests\TestCase;
use Ark4ne\OpenApi\Types\TypeResolver;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

#[CoversClass(ExceptionMappers::class)]
#[CoversClass(AttributeExceptionMapper::class)]
#[CoversClass(ConfiguredExceptionMapper::class)]
#[CoversClass(LaravelExceptionMapper::class)]
#[CoversClass(SymfonyHttpExceptionMapper::class)]
#[CoversClass(InstantiableHttpExceptionMapper::class)]
final class ExceptionMappersTest extends TestCase
{
    public function test_the_mappers_are_tried_by_decreasing_priority(): void
    {
        self::assertSame(
            [AttributeExceptionMapper::class, ConfiguredExceptionMapper::class, LaravelExceptionMapper::class, SymfonyHttpExceptionMapper::class, InstantiableHttpExceptionMapper::class],
            array_map(get_class(...), self::mappers()->all()),
        );
    }

    /**
     * @return iterable<string, array{class-string, int|null}>
     */
    public static function statuses(): iterable
    {
        yield 'attribute of a parent class' => [ChildSeatException::class, 409];
        yield 'configuration as a status' => [PaymentRequiredException::class, 402];
        yield 'Laravel: subclass of AuthenticationException' => [AuthenticationException::class, 401];
        yield 'Laravel: model not found' => [ModelNotFoundException::class, 404];
        yield 'Laravel: token mismatch' => [TokenMismatchException::class, 419];
        yield 'Laravel: throttle' => [ThrottleRequestsException::class, 429];
        yield 'Symfony: not found' => [NotFoundHttpException::class, 404];
        yield 'Symfony: too many requests' => [TooManyRequestsHttpException::class, 429];
        yield 'HttpException built without argument' => [TeapotException::class, 418];
        yield 'HttpException that needs arguments' => [HttpException::class, null];
        yield 'unknown' => [UnknownException::class, null];
    }

    /**
     * @param class-string $exception
     */
    #[DataProvider('statuses')]
    public function test_an_exception_gives_its_status(string $exception, ?int $status): void
    {
        $context = $this->context(exceptions: new ExceptionsConfiguration(map: [PaymentRequiredException::class => 402]));

        self::assertSame($status, self::mappers()->map($exception, $context)?->status);
    }

    public function test_the_configuration_gives_the_description(): void
    {
        $context = $this->context(exceptions: new ExceptionsConfiguration(map: [PaymentRequiredException::class => ['status' => 402, 'description' => 'Pay first']]));

        self::assertSame('Pay first', (new ConfiguredExceptionMapper())->map(PaymentRequiredException::class, $context)?->description);
    }

    public function test_the_most_specific_configured_class_wins(): void
    {
        $context = $this->context(exceptions: new ExceptionsConfiguration(map: [RuntimeException::class => 500, PaymentRequiredException::class => 402]));

        self::assertSame(402, (new ConfiguredExceptionMapper())->map(PaymentRequiredException::class, $context)?->status);
    }

    public function test_an_exception_attribute_without_body_gets_the_error_body(): void
    {
        $response = self::mappers()->map(ChildSeatException::class, $this->context());

        Schemas::assertSame('{"$ref":"#/components/schemas/Error"}', $response->contents['application/json'] ?? Schema::create());
    }

    public function test_an_invalid_configured_response_is_ignored(): void
    {
        $context = $this->context(exceptions: new ExceptionsConfiguration(map: [PaymentRequiredException::class => ['description' => 'no status']]));

        self::assertFalse((new ConfiguredExceptionMapper())->supports(PaymentRequiredException::class, $context));
    }

    public function test_the_error_bodies_given_shape_every_error_response(): void
    {
        $context = $this->context(exceptions: new ExceptionsConfiguration(map: [PaymentRequiredException::class => 402]));
        $mappers = self::mappers(new TestErrorBodies());

        foreach ([ModelNotFoundException::class, ValidationException::class, NotFoundHttpException::class, PaymentRequiredException::class, SeatTakenException::class] as $exception) {
            $response = $mappers->map($exception, $context);

            self::assertSame(['application/problem+json'], array_keys($response->contents ?? []), $exception);
            Schemas::assertSame(['$ref' => '#/components/schemas/Problem'], $response?->contents['application/problem+json'] ?? Schema::create(), $exception);
        }
    }

    private static function mappers(ErrorBodies $errors = new LaravelErrorBodies()): ExceptionMappers
    {
        $types = new TypeResolver();
        $samplers = Samplers::default(new NullSamplingSession(), $types);
        $models = new ResourceModels($types);
        $resources = new ResourceSchemas($models, $samplers, new SchemaInferrer(new ModelSchemas($types)), new RelationLoader($samplers));

        return ExceptionMappers::default(new DeclaredResponses($resources, new ResourceCollectionExtractor($resources, $models), $types), $errors);
    }
}
