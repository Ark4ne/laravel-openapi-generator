<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Types;

use PhpToken;

/**
 * Reads the namespaces and the `use` imports of a PHP file, per namespace block (`namespace A;` or
 * `namespace A { … }`). One instance caches the files it read.
 */
final class UseStatements
{
    /** @var array<string, list<array{line: int, namespace: string, uses: array<string, string>}>> */
    private array $files = [];

    /**
     * Imports of the namespace block that contains the given line.
     *
     * @return array<string, string> lower-cased alias → fully qualified class name
     */
    public function at(string $file, int $line): array
    {
        return $this->block($file, $line)['uses'];
    }

    /**
     * Namespace of the block that contains the given line.
     */
    public function namespaceAt(string $file, int $line): string
    {
        return $this->block($file, $line)['namespace'];
    }

    /**
     * @return array{line: int, namespace: string, uses: array<string, string>}
     */
    private function block(string $file, int $line): array
    {
        $found = ['line' => 0, 'namespace' => '', 'uses' => []];

        foreach ($this->blocks($file) as $block) {
            if ($block['line'] <= $line) {
                $found = $block;
            }
        }

        return $found;
    }

    /**
     * @return list<array{line: int, namespace: string, uses: array<string, string>}>
     */
    private function blocks(string $file): array
    {
        if (isset($this->files[$file])) {
            return $this->files[$file];
        }

        $code = is_file($file) ? (string) file_get_contents($file) : '';
        $tokens = array_values(array_filter(PhpToken::tokenize($code), static fn(PhpToken $token): bool => ! $token->isIgnorable()));
        $blocks = [];
        $line = 0;
        $namespace = '';
        $uses = [];
        $depth = 0;
        // Depth of the imports: 1 inside `namespace A { … }`.
        $level = 0;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token->text === '{' || $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $depth++;
            } elseif ($token->text === '}') {
                $depth--;
            } elseif ($token->is(T_NAMESPACE) && $depth === 0) {
                $blocks[] = ['line' => $line, 'namespace' => $namespace, 'uses' => $uses];
                $name = $tokens[$i + 1] ?? null;
                $named = $name !== null && $name->is([T_NAME_QUALIFIED, T_STRING]);
                $line = $token->line;
                $namespace = $named ? $name->text : '';
                $uses = [];
                $next = $tokens[$i + ($named ? 2 : 1)] ?? null;
                $level = $next !== null && $next->text === '{' ? 1 : 0;
            } elseif ($token->is(T_USE) && $depth === $level) {
                $i = self::use($tokens, $i + 1, $uses);
            }
        }

        $blocks[] = ['line' => $line, 'namespace' => $namespace, 'uses' => $uses];

        return $this->files[$file] = $blocks;
    }

    /**
     * Reads one `use` statement from the token after `use`, and returns the position of its `;`.
     *
     * @param list<PhpToken> $tokens
     * @param array<string, string> $uses
     */
    private static function use(array $tokens, int $i, array &$uses): int
    {
        // `function () use ($value)` of a closure written outside any brace.
        if (isset($tokens[$i]) && $tokens[$i]->text === '(') {
            return $i;
        }

        if (isset($tokens[$i]) && $tokens[$i]->is([T_FUNCTION, T_CONST])) {
            while (isset($tokens[$i]) && $tokens[$i]->text !== ';') {
                $i++;
            }

            return $i;
        }

        $prefix = '';
        $name = '';
        $alias = null;
        $count = count($tokens);

        for (; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING]) && $alias === null && ! self::after($tokens, $i, T_AS)) {
                $name = ltrim($token->text, '\\');
            } elseif ($token->is(T_STRING) && self::after($tokens, $i, T_AS)) {
                $alias = $token->text;
            } elseif ($token->text === '{') {
                $prefix = rtrim($name, '\\') . '\\';
                $name = '';
            } elseif (in_array($token->text, [',', '}', ';'], true)) {
                if ($name !== '') {
                    $full = $prefix . $name;
                    $uses[strtolower($alias ?? self::basename($full))] = $full;
                }

                $name = '';
                $alias = null;

                if ($token->text === ';') {
                    return $i;
                }
            }
        }

        return $i;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private static function after(array $tokens, int $i, int $kind): bool
    {
        return isset($tokens[$i - 1]) && $tokens[$i - 1]->is($kind);
    }

    private static function basename(string $name): string
    {
        $position = strrpos($name, '\\');

        return $position === false ? $name : substr($name, $position + 1);
    }
}
