<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Context;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Collects every diagnostic of a generation instead of stopping at the first one (Notification pattern).
 *
 * @implements IteratorAggregate<int, Diagnostic>
 */
final class Diagnostics implements Countable, IteratorAggregate
{
    /** @var list<Diagnostic> */
    private array $diagnostics = [];

    public function add(Diagnostic ...$diagnostics): void {}

    public function info(Stage $stage, string $message, ?string $route = null): void {}

    public function warning(Stage $stage, string $message, ?string $route = null): void {}

    public function error(Stage $stage, string $message, ?string $route = null): void {}

    /**
     * @return list<Diagnostic> in the order they were reported
     */
    public function all(): array
    {
        return $this->diagnostics;
    }

    /**
     * @return list<Diagnostic>
     */
    public function ofLevel(DiagnosticLevel $level): array
    {
        return [];
    }

    /**
     * Whether a diagnostic has this level or a more severe one.
     */
    public function has(DiagnosticLevel $level): bool
    {
        return false;
    }

    public function isEmpty(): bool
    {
        return true;
    }

    public function count(): int
    {
        return 0;
    }

    /**
     * @return Traversable<int, Diagnostic>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator([]);
    }
}
