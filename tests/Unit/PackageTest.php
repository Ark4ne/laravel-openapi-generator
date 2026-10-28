<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PackageTest extends TestCase
{
    public function test_composer_maps_the_package_namespace_to_src(): void
    {
        $autoload = self::composer()['autoload'] ?? null;

        self::assertIsArray($autoload);
        self::assertSame(['Ark4ne\\OpenApi\\' => 'src/'], $autoload['psr-4'] ?? null);
    }

    public function test_composer_targets_php_8_3_and_laravel_13(): void
    {
        $require = self::composer()['require'] ?? null;

        self::assertIsArray($require);
        self::assertSame('^8.3', $require['php'] ?? null);

        foreach ($require as $package => $constraint) {
            if (is_string($package) && str_starts_with($package, 'illuminate/')) {
                self::assertSame('^13.0', $constraint, $package);
            }
        }
    }

    public function test_composer_requires_no_unmaintained_dependency(): void
    {
        $packages = array_keys(array_merge(
            (array) (self::composer()['require'] ?? []),
            (array) (self::composer()['require-dev'] ?? []),
        ));

        foreach (['goldspecdigital/oooas', 'phpdocumentor/reflection-docblock', 'phpdocumentor/type-resolver', 'justinrainbow/json-schema', 'laravel/framework'] as $removed) {
            self::assertNotContains($removed, $packages);
        }
    }

    /**
     * @return array<mixed>
     */
    private static function composer(): array
    {
        $composer = json_decode((string) file_get_contents(__DIR__ . '/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($composer);

        return $composer;
    }
}
