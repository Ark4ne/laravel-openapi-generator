<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Extraction\Exceptions;

use Ark4ne\OpenApi\Configuration\ExceptionsConfiguration;
use Ark4ne\OpenApi\Extraction\Exceptions\AttributeExceptionMapper;
use Ark4ne\OpenApi\Extraction\Exceptions\ConfiguredExceptionMapper;
use Ark4ne\OpenApi\Extraction\Exceptions\ExceptionMappers;
use Ark4ne\OpenApi\Extraction\Exceptions\InstantiableHttpExceptionMapper;
use Ark4ne\OpenApi\Extraction\Exceptions\LaravelExceptionMapper;
use Ark4ne\OpenApi\Extraction\Exceptions\SymfonyHttpExceptionMapper;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Exceptions\ChildSeatException;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Exceptions\PaymentRequiredException;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Exceptions\TeapotException;
use Ark4ne\OpenApi\Tests\Fixtures\Responses\Exceptions\UnknownException;
use Ark4ne\OpenApi\Tests\TestCase;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
            array_map(get_class(...), ExceptionMappers::default()->all()),
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

        self::assertSame($status, ExceptionMappers::default()->map($exception, $context)?->status);
    }

    public function test_the_configuration_gives_the_description(): void
    {
        $context = $this->context(exceptions: new ExceptionsConfiguration(map: [PaymentRequiredException::class => ['status' => 402, 'description' => 'Pay first']]));

        self::assertSame('Pay first', (new ConfiguredExceptionMapper())->map(PaymentRequiredException::class, $context)?->description);
    }

    public function test_an_invalid_configured_response_is_ignored(): void
    {
        $context = $this->context(exceptions: new ExceptionsConfiguration(map: [PaymentRequiredException::class => ['description' => 'no status']]));

        self::assertFalse((new ConfiguredExceptionMapper())->supports(PaymentRequiredException::class, $context));
    }
}
