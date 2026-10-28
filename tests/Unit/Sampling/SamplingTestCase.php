<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Sampling;

use Ark4ne\OpenApi\Configuration\SamplingConfiguration;
use Ark4ne\OpenApi\Sampling\DatabaseSamplingSession;
use Ark4ne\OpenApi\Tests\TestCase;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `articles` and `authors` tables of the sampling fixtures, on the in-memory SQLite connection `testing`.
 */
abstract class SamplingTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('articles', static function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('status');
            $table->decimal('rating', 4, 2);
            $table->json('meta');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('authors', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->date('born_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @param list<string> $connections
     */
    protected function samplingSession(SamplingConfiguration $configuration = new SamplingConfiguration(), array $connections = ['testing']): DatabaseSamplingSession
    {
        $resolver = $this->app?->make(ConnectionResolverInterface::class);
        self::assertInstanceOf(ConnectionResolverInterface::class, $resolver);

        return new DatabaseSamplingSession($resolver, $connections, $configuration);
    }
}
