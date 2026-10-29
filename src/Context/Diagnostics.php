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

    public function add(Diagnostic ...$diagnostics): void
    {
        foreach ($diagnostics as $diagnostic) {
            $this->diagnostics[] = $diagnostic;
        }
    }

    public function info(Stage $stage, string $message, ?string $route = null): void
    {
        $this->add(new Diagnostic(DiagnosticLevel::Info, $stage, $message, $route));
    }

    public function warning(Stage $stage, string $message, ?string $route = null): void
    {
        $this->add(new Diagnostic(DiagnosticLevel::Warning, $stage, $message, $route));
    }

    public function error(Stage $stage, string $message, ?string $route = null): void
    {
        $this->add(new Diagnostic(DiagnosticLevel::Error, $stage, $message, $route));
    }

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
        return array_values(array_filter($this->diagnostics, static fn(Diagnostic $diagnostic): bool => $diagnostic->level === $level));
    }

    /**
     * Whether a diagnostic has this level or a more severe one.
     */
    public function has(DiagnosticLevel $level): bool
    {
        foreach ($this->diagnostics as $diagnostic) {
            if ($diagnostic->level->isAtLeast($level)) {
                return true;
            }
        }

        return false;
    }

    public function isEmpty(): bool
    {
        return $this->diagnostics === [];
    }

    public function count(): int
    {
        return count($this->diagnostics);
    }

    /**
     * @return Traversable<int, Diagnostic>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->diagnostics);
    }
}
