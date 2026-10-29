<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Context;

enum DiagnosticLevel: string
{
    /** Something the generator skipped on purpose (unknown validation rule…). Shown with `-v`. */
    case Info = 'info';

    /** Something the document describes partially. `--strict` makes the generation fail. */
    case Warning = 'warning';

    /** Something the generator could not describe at all. */
    case Error = 'error';

    /**
     * Whether this level is as severe as the given one, or more.
     */
    public function isAtLeast(self $level): bool
    {
        return $this->severity() >= $level->severity();
    }

    private function severity(): int
    {
        return match ($this) {
            self::Info => 0,
            self::Warning => 1,
            self::Error => 2,
        };
    }
}
