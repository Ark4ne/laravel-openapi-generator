<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Tests\Fixtures\Variants;

use Ark4ne\OpenApi\Attributes\Since;
use Ark4ne\OpenApi\Attributes\Until;

/**
 * Labels exist from the revision `2026-12-01`, names until `2026-09-01`: the path and the component of each are absent
 * from the documents of the other revisions.
 */
final class LabelController
{
    #[Since(ApiRevision::Labels)]
    public function show(): LabelResource
    {
        return new LabelResource(null);
    }

    /**
     * An action whose resource cannot be rendered: a warning when the document of a variant reads it.
     */
    #[Since(ApiRevision::Labels)]
    public function broken(): BrokenResource
    {
        return new BrokenResource(null);
    }

    #[Until(ApiRevision::Initial)]
    public function legacy(): NameResource
    {
        return new NameResource(null);
    }
}
