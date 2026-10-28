<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Configuration;

use Ark4ne\OpenApi\Configuration\ExceptionsConfiguration;
use Ark4ne\OpenApi\Configuration\StrategyConfiguration;
use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(StrategyConfiguration::class)]
#[CoversClass(ExceptionsConfiguration::class)]
final class StrategyAndExceptionsConfigurationTest extends TestCase
{
    public function test_a_strategy_is_read_with_its_options(): void
    {
        $strategy = StrategyConfiguration::fromArray('documents.v1.strategies.tags', ['by' => 'uri', 'pattern' => '/^api\/(\w+)/', 'other' => 1], 'controller');

        self::assertSame('uri', $strategy->by);
        self::assertSame(['pattern' => '/^api\/(\w+)/', 'other' => 1], $strategy->options);
        self::assertSame('/^api\/(\w+)/', $strategy->pattern());
    }

    public function test_a_strategy_without_by_takes_the_default(): void
    {
        $strategy = StrategyConfiguration::fromArray('documents.v1.strategies.summary', [], 'doc');

        self::assertSame('doc', $strategy->by);
        self::assertNull($strategy->pattern());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidStrategies(): iterable
    {
        yield 'empty by' => [['by' => ''], 'openapi.documents.v1.strategies.tags.by: expected a strategy name or class, got ""'];
        yield 'by not a string' => [['by' => 1], 'openapi.documents.v1.strategies.tags.by: expected a strategy name or class, got 1'];
        yield 'invalid pattern' => [['pattern' => '/(/'], 'openapi.documents.v1.strategies.tags.pattern: expected a regular expression, got "/(/"'];
        yield 'pattern not a string' => [['pattern' => ['/a/']], 'openapi.documents.v1.strategies.tags.pattern: expected a regular expression, got array'];
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[DataProvider('invalidStrategies')]
    public function test_an_invalid_strategy_is_rejected(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        StrategyConfiguration::fromArray('documents.v1.strategies.tags', $config, 'controller');
    }

    public function test_the_exceptions_entry_is_read(): void
    {
        self::assertEquals(new ExceptionsConfiguration(), ExceptionsConfiguration::fromArray('exceptions', []));
        self::assertEquals(
            new ExceptionsConfiguration(false, [RuntimeException::class => 409]),
            ExceptionsConfiguration::fromArray('exceptions', ['read_throws' => false, 'map' => [RuntimeException::class => 409]]),
        );
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidExceptions(): iterable
    {
        yield 'unknown key' => [['read' => true], 'openapi.exceptions: expected the keys read_throws, map, got "read"'];
        yield 'read_throws not a boolean' => [['read_throws' => 'yes'], 'openapi.exceptions.read_throws: expected a boolean, got "yes"'];
        yield 'map as a list' => [['map' => [409]], 'openapi.exceptions.map: expected a map of exception class to response, got array'];
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[DataProvider('invalidExceptions')]
    public function test_an_invalid_exceptions_entry_is_rejected(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        ExceptionsConfiguration::fromArray('exceptions', $config);
    }
}
