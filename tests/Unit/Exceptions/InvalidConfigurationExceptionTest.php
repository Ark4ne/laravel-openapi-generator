<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Exceptions;

use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(InvalidConfigurationException::class)]
final class InvalidConfigurationExceptionTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function receivedValues(): iterable
    {
        yield 'string' => ['3.3', '"3.3"'];
        yield 'integer' => [3, '3'];
        yield 'float' => [3.5, '3.5'];
        yield 'true' => [true, 'true'];
        yield 'false' => [false, 'false'];
        yield 'null' => [null, 'null'];
        yield 'array' => [['a'], 'array'];
        yield 'object' => [new stdClass(), 'stdClass'];
    }

    #[DataProvider('receivedValues')]
    public function test_the_message_names_the_key_the_expected_and_the_received_value(mixed $received, string $written): void
    {
        $exception = InvalidConfigurationException::forKey('documents.v1.openapi', 'one of 3.0.0, 3.2.0', $received);

        self::assertSame("openapi.documents.v1.openapi: expected one of 3.0.0, 3.2.0, got {$written}", $exception->getMessage());
    }
}
