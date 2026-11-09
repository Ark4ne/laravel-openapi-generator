<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiRevisions;

use Ark4ne\JsonApi\Definition\Revision;

enum ApiRevision: string implements Revision
{
    case Initial = '2026-09-01';
    case Labels = '2026-12-01';
    case Members = '2027-01-15';

    public function description(): string
    {
        return match ($this) {
            self::Initial => 'First published revision',
            self::Labels => 'Names become labels',
            self::Members => 'Members replace writers',
        };
    }
}
