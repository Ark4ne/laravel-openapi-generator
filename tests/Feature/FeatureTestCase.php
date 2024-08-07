<?php

namespace Test\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Test\Support\UseLocalApp;
use Test\TestCase;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase, UseLocalApp;

    protected function beforeRefreshingDatabase()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../app/migrations.php');
    }

    public function setUp(): void
    {
        parent::setUp();
    }
}
