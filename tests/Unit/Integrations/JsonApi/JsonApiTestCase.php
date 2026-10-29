<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit\Integrations\JsonApi;

use Ark4ne\OpenApi\Tests\Unit\Sampling\SamplingTestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `users`, `posts` and `comments` tables of the JSON:API fixtures; the tests carry the group `json-api`, left out
 * when the package is not installed.
 */
abstract class JsonApiTestCase extends SamplingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        Schema::create('posts', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('title');
            $table->text('content');
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('comments', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('post_id');
            $table->text('content');
            $table->timestamps();
        });
    }
}
