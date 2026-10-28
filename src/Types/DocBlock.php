<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Types;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;

/**
 * A parsed PHPDoc comment: its summary, its description and its tags.
 *
 * The summary is the first paragraph, cut after its first sentence when that sentence ends a line (PSR-5); the
 * description is the rest of the text before the tags.
 */
final readonly class DocBlock
{
    public function __construct(
        public PhpDocNode $node,
    ) {}

    public static function empty(): self
    {
        return new self(new PhpDocNode([]));
    }

    public function summary(): ?string
    {
        return $this->split()[0];
    }

    public function description(): ?string
    {
        return $this->split()[1];
    }

    public function isDeprecated(): bool
    {
        return $this->node->getDeprecatedTagValues() !== [];
    }

    /**
     * The text of `@deprecated`, when it has one.
     */
    public function deprecation(): ?string
    {
        $tag = $this->node->getDeprecatedTagValues()[0] ?? null;

        return $tag === null || trim((string) $tag) === '' ? null : trim((string) $tag);
    }

    /**
     * @return array{string|null, string|null}
     */
    private function split(): array
    {
        $texts = [];

        foreach ($this->node->children as $child) {
            if (! $child instanceof PhpDocTextNode) {
                break;
            }

            $texts[] = $child->text;
        }

        $text = trim(str_replace("\r\n", "\n", implode("\n", $texts)));

        if ($text === '') {
            return [null, null];
        }

        $paragraphs = preg_split('/\n\s*\n/', $text);
        $paragraphs = $paragraphs === false ? [$text] : $paragraphs;
        $first = array_shift($paragraphs);

        if (preg_match('/^(.+?\.)\n(.+)$/s', $first, $matches) === 1) {
            $first = $matches[1];
            array_unshift($paragraphs, $matches[2]);
        }

        $summary = trim((string) preg_replace('/\s*\n\s*/', ' ', $first));
        $description = trim(implode("\n\n", $paragraphs));

        return [$summary, $description === '' ? null : $description];
    }
}
