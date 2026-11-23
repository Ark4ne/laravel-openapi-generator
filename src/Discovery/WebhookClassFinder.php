<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Discovery;

use Ark4ne\OpenApi\Attributes\Exclude;
use Ark4ne\OpenApi\Attributes\Webhook;
use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use FilesystemIterator;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Throwable;

/**
 * The payload classes of the webhooks of a document: the concrete `JsonResource` classes marked `#[Webhook]`,
 * declared in the PHP files under `webhooks.paths` or listed in `webhooks.classes`. Nothing else reads them.
 *
 * - A file is tokenized only when its text contains `Webhook`, so that scanning `app/` autoloads no unrelated class.
 * - An abstract class and a class that is not a `JsonResource` (a controller marked `#[Webhook]`, whose routes are
 *   documented as route webhooks) are skipped silently.
 * - A scanned class that cannot be loaded is skipped with a warning naming its file; a listed class that is not a
 *   concrete `JsonResource` marked `#[Webhook]` is an invalid configuration.
 * - A class marked `#[Exclude]` is left out of every document, listed or found.
 * - The classes are sorted by name, each once, so that the document does not depend on the filesystem.
 * - `keeps()` chooses the webhooks of a document by their name (`webhooks.include`, `webhooks.exclude`).
 */
final readonly class WebhookClassFinder
{
    /**
     * The payload classes of the document being generated: those listed, then those found under the paths.
     *
     * @return list<class-string<JsonResource>>
     *
     * @throws InvalidConfigurationException
     */
    public function find(GenerationContext $context): array
    {
        $configuration = $context->document->webhooks;
        $found = [];

        foreach ($this->listed($context->document) as $class) {
            $found[$class] = ! self::excluded($class);
        }

        foreach ($configuration->paths as $path) {
            foreach (self::files($path) as $file) {
                foreach (self::classesIn($file) as $class) {
                    try {
                        $declares = self::declares($class);
                    } catch (Throwable $error) {
                        $context->diagnostics->warning(Stage::Discovery, "cannot load {$class} ({$file}), skipped from the webhooks: {$error->getMessage()}");

                        continue;
                    }

                    if ($declares) {
                        $found[$class] = ! self::excluded($class);
                    }
                }
            }
        }

        $found = array_keys(array_filter($found));
        sort($found);

        /** @var list<class-string<JsonResource>> $found */
        return $found;
    }

    /**
     * Whether the webhook of this name is in the document: it matches `webhooks.include` (every name when empty) and
     * no mask of `webhooks.exclude`.
     */
    public function keeps(string $name, GenerationContext $context): bool
    {
        $webhooks = $context->document->webhooks;

        return ($webhooks->include === [] || Str::is($webhooks->include, $name)) && ! Str::is($webhooks->exclude, $name);
    }

    /**
     * The classes listed in `webhooks.classes` of the document, checked: run before any document is generated.
     *
     * @return list<class-string<JsonResource>>
     *
     * @throws InvalidConfigurationException when one is not a concrete JsonResource marked #[Webhook]
     */
    public function listed(DocumentConfiguration $document): array
    {
        $listed = [];

        foreach ($document->webhooks->classes as $class) {
            $listed[] = self::declares($class) ? $class : throw InvalidConfigurationException::forKey("documents.{$document->name}.webhooks.classes", 'concrete JsonResource classes marked #[' . Webhook::class . ']', $class);
        }

        /** @var list<class-string<JsonResource>> $listed */
        return $listed;
    }

    /**
     * Whether `$class` is a concrete `JsonResource` marked `#[Webhook]` (autoloaded).
     */
    private static function declares(string $class): bool
    {
        if (! class_exists($class) || ! is_subclass_of($class, JsonResource::class)) {
            return false;
        }

        $reflection = new ReflectionClass($class);

        return ! $reflection->isAbstract() && $reflection->getAttributes(Webhook::class) !== [];
    }

    private static function excluded(string $class): bool
    {
        return class_exists($class) && (new ReflectionClass($class))->getAttributes(Exclude::class) !== [];
    }

    /**
     * The PHP files at `$path` that mention `Webhook`: itself when a file, those under it when a directory.
     *
     * @return list<string>
     */
    private static function files(string $path): array
    {
        if (is_file($path)) {
            return self::mentions($path) ? [$path] : [];
        }

        if (! is_dir($path)) {
            return [];
        }

        $files = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && self::mentions($file->getPathname())) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private static function mentions(string $file): bool
    {
        return str_contains((string) file_get_contents($file), 'Webhook');
    }

    /**
     * The fully qualified names of the classes declared in the file (never an anonymous class, never `Foo::class`).
     *
     * @return list<string>
     */
    private static function classesIn(string $file): array
    {
        $tokens = array_values(PhpToken::tokenize((string) file_get_contents($file)));
        $namespace = '';
        $classes = [];

        foreach ($tokens as $index => $token) {
            if ($token->is(T_NAMESPACE)) {
                $namespace = self::nextName($tokens, $index) . '\\';
            } elseif ($token->is(T_CLASS)) {
                // An anonymous class (`new class`) and a `Foo::class` are followed by no name.
                $name = self::nextName($tokens, $index);

                if ($name !== '') {
                    // A global `namespace { }` block gives the prefix `\`.
                    $classes[] = ltrim($namespace . $name, '\\');
                }
            }
        }

        return $classes;
    }

    /**
     * The name following the token at `$index`, `''` when the next meaningful token is not a name.
     *
     * @param list<PhpToken> $tokens
     */
    private static function nextName(array $tokens, int $index): string
    {
        for ($i = $index + 1; isset($tokens[$i]); ++$i) {
            if (! $tokens[$i]->isIgnorable()) {
                return $tokens[$i]->is([T_STRING, T_NAME_QUALIFIED]) ? $tokens[$i]->text : '';
            }
        }

        return '';
    }
}
