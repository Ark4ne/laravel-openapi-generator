<?php

namespace Test\Concerns;

use JsonSchema\Constraints\BaseConstraint;
use JsonSchema\Validator;

trait AssertOpenApi
{
    private static function schema(): object
    {
        static $schema;

        return $schema ??= json_decode(file_get_contents(
            __DIR__ . '/../../vendor/goldspecdigital/oooas/schemas/v3.0.json'
        ), false, 512, JSON_THROW_ON_ERROR);
    }

    public function assertOpenapi(object $data): void
    {
        $validator = new Validator();
        $validator->validate($data, self::schema());

        $errors = collect($validator->getErrors())->groupBy('pointer')->all();
        $lists = [];
        foreach ($errors as $pointer => $error) {
            $lists[] = "$pointer:";
            foreach ($error as $e) {
                $lists[] = "  [{$e['constraint']}] {$e['message']}";
            }
        }
        $this->assertTrue($validator->isValid(), implode("\n", $lists));
    }

    public function assertOpenapiArray(array $data): void
    {
        $this->assertOpenapi(BaseConstraint::arrayToObjectRecursive($data));
    }

    public function assertOpenapiFile(string $file): void
    {
        $this->assertFileExists($file);

        $data = json_decode(file_get_contents($file), false, 512, JSON_THROW_ON_ERROR);

        $this->assertOpenapi($data);
    }
}
