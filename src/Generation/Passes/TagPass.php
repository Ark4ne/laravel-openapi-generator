<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation\Passes;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Description\TagDescription;
use Ark4ne\OpenApi\Generation\Pass;

/**
 * The tags of the document: those it declares, then those of the operations in order of appearance; the details
 * of `#[Tag]` fill what is not known yet.
 */
final readonly class TagPass implements Pass
{
    public function process(DocumentDescription $document, GenerationContext $context): DocumentDescription
    {
        /** @var array<string, TagDescription> $tags */
        $tags = [];

        foreach ($document->tags as $tag) {
            $tags[$tag->name] = $tag;
        }

        foreach ([...$document->operations, ...$document->webhooks] as $operation) {
            foreach ($operation->tags as $name) {
                $tags[$name] ??= TagDescription::create($name);
            }

            foreach ($operation->declaredTags as $declared) {
                $tags[$declared->name] = self::fill($tags[$declared->name] ?? TagDescription::create($declared->name), $declared);
            }
        }

        return $document->tags(...array_values($tags));
    }

    private static function fill(TagDescription $tag, TagDescription $declared): TagDescription
    {
        return $tag
            ->description($tag->description ?? $declared->description)
            ->summary($tag->summary ?? $declared->summary)
            ->kind($tag->kind ?? $declared->kind)
            ->parent($tag->parent ?? $declared->parent)
            ->externalDocs($tag->externalDocs ?? $declared->externalDocs);
    }
}
