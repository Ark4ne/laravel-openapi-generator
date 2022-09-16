<?php

namespace Test\Feature;

use JsonSchema\Constraints\BaseConstraint;
use JsonSchema\Validator;

class GenerateTest extends FeatureTestCase
{
    public function testGenerate(): void
    {
        $config = $this->app['config']['openapi'];
        $file = "{$config['output-dir']}/{$config['versions']['v1']['output-file']}";

        $this
            ->artisan('openapi:generate --force')
            ->assertSuccessful();

        $this->assertFileExists($file);

        $data = BaseConstraint::arrayToObjectRecursive(
            json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR)
        );

        $schema = json_decode(file_get_contents(
            __DIR__ . '/../../vendor/goldspecdigital/oooas/schemas/v3.0.json'
        ), false, 512, JSON_THROW_ON_ERROR);

        $validator = new Validator();
        $validator->validate($data, $schema);

        $this->assertTrue($validator->isValid());
    }
}
