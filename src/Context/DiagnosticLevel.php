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
}
