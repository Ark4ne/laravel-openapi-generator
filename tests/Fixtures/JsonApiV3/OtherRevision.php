<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3;

use Ark4ne\ApiRevisions\Revisions\IsRevision;
use Ark4ne\ApiRevisions\Revisions\Revision;

/**
 * A revision enum the application does not configure.
 */
enum OtherRevision: string implements Revision
{
    use IsRevision;

    case First = 'first';

    public static function order(): array
    {
        return [self::First];
    }
}
