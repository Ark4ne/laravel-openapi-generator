<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\JsonApiV3\Types;

use Ark4ne\ApiRevisions\Laravel\Resources\RevisionedResource;
use Ark4ne\ApiRevisions\Schema\Revisioned;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;

/**
 * A nested revisioned resource whose declaration cannot be compiled.
 */
final class BrokenNestedResource extends JsonResource implements Revisioned
{
    use RevisionedResource;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        throw new RuntimeException('no shape');
    }
}
