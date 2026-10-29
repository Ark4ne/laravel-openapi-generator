<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Sampling;

use Ark4ne\OpenApi\Configuration\SamplingConfiguration;
use Ark4ne\OpenApi\Sampling\DatabaseSamplingSession;
use Ark4ne\OpenApi\Tests\Fixtures\Sampling\Factories\ArticleFactory;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(DatabaseSamplingSession::class)]
final class DatabaseSamplingSessionTest extends SamplingTestCase
{
    public function test_what_is_written_during_the_session_is_rolled_back(): void
    {
        $session = $this->samplingSession();

        $count = $session->run(static function () use ($session): int {
            self::assertTrue($session->canPersist('testing'));
            ArticleFactory::new()->createOne();

            return DB::table('articles')->count();
        }, $this->context());

        self::assertSame(1, $count);
        self::assertSame(0, DB::table('articles')->count());
    }

    public function test_the_session_is_rolled_back_when_the_generation_fails(): void
    {
        $session = $this->samplingSession();

        try {
            $session->run(static function (): never {
                ArticleFactory::new()->createOne();

                throw new RuntimeException('failed');
            }, $this->context());
        } catch (RuntimeException $exception) {
            self::assertSame('failed', $exception->getMessage());
        }

        self::assertSame(0, DB::table('articles')->count());
        self::assertFalse($session->canPersist('testing'));
    }

    public function test_nothing_can_be_persisted_outside_the_session(): void
    {
        self::assertFalse($this->samplingSession()->canPersist('testing'));
    }

    public function test_nothing_can_be_persisted_without_transactions(): void
    {
        $session = $this->samplingSession(new SamplingConfiguration(transactions: false));

        self::assertFalse($session->run(static fn(): bool => $session->canPersist('testing'), $this->context()));
    }

    public function test_a_driver_that_is_not_configured_gets_no_transaction(): void
    {
        $session = $this->samplingSession(new SamplingConfiguration(connections: ['pgsql']));

        self::assertFalse($session->run(static fn(): bool => $session->canPersist('testing'), $this->context()));
    }

    public function test_the_default_connection_is_used_without_name(): void
    {
        $session = $this->samplingSession();

        self::assertTrue($session->run(static fn(): bool => $session->canPersist(), $this->context()));
    }

    public function test_an_unreachable_connection_is_reported_and_ignored(): void
    {
        $session = $this->samplingSession(connections: ['testing', 'missing']);
        $context = $this->context();

        self::assertTrue($session->run(static fn(): bool => $session->canPersist('testing') && ! $session->canPersist('missing'), $context));
        self::assertCount(1, $context->diagnostics);
        self::assertStringStartsWith('[warning] sampling: connection missing unreachable, sampled without transaction: ', (string) $context->diagnostics->all()[0]);
    }

    public function test_the_seed_gives_the_same_values_twice(): void
    {
        $session = $this->samplingSession(new SamplingConfiguration(seed: 42));

        $first = $session->run(static fn(): string => ArticleFactory::new()->makeOne()->title, $this->context());
        $second = $session->run(static fn(): string => ArticleFactory::new()->makeOne()->title, $this->context());

        self::assertSame($first, $second);
    }
}
