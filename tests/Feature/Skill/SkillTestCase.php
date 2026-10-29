<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Feature\Skill;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Laravel\GeneratorFactory;
use Ark4ne\OpenApi\Output\DocumentValidator;
use Ark4ne\OpenApi\Tests\Feature\Laravel\LaravelTestCase;
use Ark4ne\OpenApiWriter\Enums\Version;
use Ark4ne\OpenApiWriter\Validation\OpisJsonSchemaValidator;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Route;

/**
 * An agent skill shipped with the package (`skills/<name>/`): its front matter, the links between its files, and the
 * `php` blocks of its examples run in an application.
 */
abstract class SkillTestCase extends LaravelTestCase
{
    /**
     * The directory of the skill, which is also its name.
     */
    abstract protected static function skill(): string;

    /**
     * @return list<string>
     */
    abstract protected static function files(): array;

    public function test_the_skill_declares_its_name_and_when_to_use_it(): void
    {
        self::assertSame(1, preg_match('/\A---\nname: ' . preg_quote(static::skill(), '/') . '\ndescription: (.+)\n---\n/', self::read('SKILL.md'), $matches));
        self::assertLessThanOrEqual(1024, strlen($matches[1]));
        self::assertStringContainsString('Ark4ne\OpenApi', $matches[1]);
    }

    public function test_every_link_between_the_files_of_the_skill_resolves(): void
    {
        foreach (static::files() as $file) {
            preg_match_all('/\]\(([^)\s]+)\)/', self::read($file), $links);

            foreach ($links[1] as $link) {
                if (preg_match('#^[a-z]+://#', $link) === 1) {
                    continue;
                }

                [$path, $anchor] = array_pad(explode('#', $link, 2), 2, null);
                $target = $path === '' ? self::directory() . $file : self::directory() . $path;

                self::assertFileExists($target, "{$file}: {$link}");

                if ($anchor !== null) {
                    self::assertContains($anchor, self::anchors((string) file_get_contents($target)), "{$file}: {$link}");
                }
            }
        }
    }

    /**
     * Runs the block of an example as `routes/api.php` (its routes under the `api` prefix), then generates and
     * validates its document in each line with the published configuration and the given overrides; returns the 3.1
     * one.
     *
     * @param array<string, mixed> $config dotted keys under `openapi`
     *
     * @return array<array-key, mixed>
     */
    protected function example(string $file, string $title, array $config = []): array
    {
        $code = self::code($file, $title);
        Route::prefix('api')->group(static function () use ($code): void {
            eval($code);
        });
        $repository = $this->app?->make(Repository::class);
        self::assertInstanceOf(Repository::class, $repository);
        // Written in 3.0 too: what 3.0 cannot write (webhooks) is dropped instead of failing.
        $repository->set('openapi.documents.v1.strict', false);
        $repository->set('openapi.sampling.transactions', false);

        foreach ($config as $key => $value) {
            $repository->set("openapi.{$key}", $value);
        }

        $written = [];

        foreach (['3.0' => Version::V3_0_4, '3.1' => Version::V3_1_2, '3.2' => Version::V3_2_0] as $line => $version) {
            $repository->set('openapi.documents.v1.openapi', $version->value);
            $settings = $repository->get('openapi');
            self::assertIsArray($settings);
            $configuration = Configuration::fromArray($settings);
            $factory = $this->app?->make(GeneratorFactory::class);
            self::assertInstanceOf(GeneratorFactory::class, $factory);
            $generated = $factory->make($configuration)->generate($configuration, 'v1');
            $violations = (new DocumentValidator(new OpisJsonSchemaValidator()))->validate($generated->openApi, $generated->context);

            self::assertSame([], array_map(strval(...), $violations->errors()), "{$title} ({$line})");
            $written[$line] = $generated->openApi;
        }

        return $written['3.1']->toArray(Version::V3_1_2);
    }

    /**
     * The runnable `php` block of an example (the `## <title>` section), without its `skip` blocks.
     */
    protected static function code(string $file, string $title): string
    {
        $sections = preg_split('/^## /m', self::read($file));

        foreach ($sections === false ? [] : $sections as $section) {
            if (str_starts_with($section, "{$title}\n") && preg_match('/^```php\n(.*?)^```$/ms', $section, $block) === 1) {
                return $block[1];
            }
        }

        self::fail("{$file} has no runnable block for {$title}.");
    }

    protected static function read(string $file): string
    {
        return (string) file_get_contents(self::directory() . $file);
    }

    private static function directory(): string
    {
        return dirname(__DIR__, 3) . '/skills/' . static::skill() . '/';
    }

    /**
     * The anchors GitHub gives to the headings of a Markdown file.
     *
     * @return list<string>
     */
    private static function anchors(string $markdown): array
    {
        preg_match_all('/^#{1,6} (.+)$/m', $markdown, $headings);

        return array_map(static fn(string $heading): string => str_replace(' ', '-', (string) preg_replace('/[^a-z0-9 _-]/', '', strtolower($heading))), $headings[1]);
    }
}
