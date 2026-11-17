<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3;

use Ark4ne\ApiRevisions\Revisions\IsRevision;
use Ark4ne\ApiRevisions\Revisions\Meta;
use Ark4ne\ApiRevisions\Revisions\Revision;

/**
 * The revisions of the scenario; the last one of `order()` is the head.
 */
enum ApiRevision: string implements Revision
{
    use IsRevision;

    #[Meta('First published revision')]
    case Initial = '2026-09-01';

    #[Meta('Names become labels')]
    case Labels = '2026-12-01';

    #[Meta('Members replace writers')]
    case Members = '2027-01-15';

    public static function order(): array
    {
        return [self::Initial, self::Labels, self::Members];
    }
}
