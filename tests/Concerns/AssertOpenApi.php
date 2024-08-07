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

    public function assertOpenapiFileIs(string $expected, string $actual): void
    {
        $this->assertFileExists($actual);

        $expected = json_decode(file_get_contents($expected), true, 512, JSON_THROW_ON_ERROR);
        $actual = json_decode(file_get_contents($actual), true, 512, JSON_THROW_ON_ERROR);

        $depths = [
            'paths' => 2
        ];

        $clean = function (array &$arr, string $key, mixed $value = null) use (&$clean) {
            if (isset($arr[$key]) && (!is_array($arr[$key]) || isset($arr[$key][0]))) {
                $arr[$key] = $value;
            }

            foreach ($arr as &$item) {
                if (is_array($item)) {
                    $clean($item, $key, $value);
                }
            }
        };

        $clean($expected, 'example', null);
        $clean($actual, 'example', null);
        $clean($expected, 'name', 'test');
        $clean($actual, 'name', 'test');

        $in = function (array $expected, array $actual, int $depth, string $path) use (&$in) {
            foreach ($expected as $key => $value) {
                $this->assertArrayHasKey($key, $actual);
                if ($depth) {
                    $in($value, $actual[$key], $depth - 1, $path ? "$path.$key" : $key);
                } else {
                    $this->assertEquals($value, $actual[$key], $path ? "$path.$key" : $key);
                }
            }
        };

        foreach ($expected as $key => $item) {
            $this->assertArrayHasKey($key, $actual);
            if (is_string($item)) $this->assertEquals($item, $actual[$key]);
            else $in($item, $actual[$key], $depths[$key] ?? 0, '');
        }
    }
}
