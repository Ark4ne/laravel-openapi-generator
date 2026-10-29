<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Passes;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\TagDescription;
use Ark4ne\OpenApi\Generation\Pass;

/**
 * The parents of the tags (`Tag.parent`, set from the group strategy at extraction) are declared tags too. Before
 * 3.2 the writer writes them as `x-tagGroups` (`vendor_extensions: redoc`), or rejects or drops them.
 */
final readonly class TagGroupPass implements Pass
{
    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        $names = array_map(static fn(TagDescription $tag): string => $tag->name, $document->tags);

        foreach ($document->tags as $tag) {
            if ($tag->parent !== null && ! in_array($tag->parent, $names, true)) {
                $document = $document->addTags(TagDescription::create($tag->parent));
                $names[] = $tag->parent;
            }
        }

        return $document;
    }
}
