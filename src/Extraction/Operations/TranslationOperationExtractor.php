<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Operations;

use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Description\OperationDescription;
use Ark4ne\OpenApi\Description\TagDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Illuminate\Contracts\Translation\Translator;

/**
 * Translates the summary, the description and the tags into the locale of the generation: each text may be a
 * translation key (`docs.users.index`). A text without translation stays as written. Runs last.
 */
final readonly class TranslationOperationExtractor implements OperationExtractor
{
    public const PRIORITY = 1000;

    public function __construct(
        private Translator $translator,
    ) {}

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(OperationContext $operation): bool
    {
        return true;
    }

    public function extract(OperationContext $operation, OperationDescription $description, GenerationContext $context): OperationDescription
    {
        $translate = fn(?string $text): ?string => $this->translate($text, $context->locale);

        return $description
            ->summary($translate($description->summary))
            ->description($translate($description->description))
            ->tags(...array_map(fn(string $tag): string => $this->translate($tag, $context->locale) ?? $tag, $description->tags))
            ->declaredTags(...array_map(static fn(TagDescription $tag): TagDescription => $tag
                ->name($translate($tag->name) ?? $tag->name)
                ->description($translate($tag->description))
                ->summary($translate($tag->summary)), $description->declaredTags));
    }

    private function translate(?string $text, ?string $locale): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $translated = $this->translator->get($text, [], $locale);

        return is_string($translated) ? $translated : $text;
    }
}
