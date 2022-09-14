<?php

namespace Test\Feature;

class GenerateTest extends FeatureTestCase
{
    public function testGenerate()
    {
        $this
            ->artisan('openapi:generate --force')
            ->assertSuccessful();
    }
}
