<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Configuration;

use BackedEnum;
use LogicException;
use UnitEnum;

/**
 * One value of the variant dimension of a document (`documents.<name>.variants`): the document is generated once per
 * variant. The core gives it no meaning; an integration or an extension recognises its own values (a revision of an
 * API, a tenant…) and ignores the others.
 *
 * Variants are ordered by their declaration (enum cases, list items). The head, the document generated without
 * variant (`null`), is newer than every variant.
 */
final readonly class Variant
{
    /**
     * @param UnitEnum|string $value the enum case or the string declared
     * @param string $name the value of a backed case, the name of a pure case, the string itself; written in file names
     *        and in the documents
     * @param int $position the index of the variant in the declaration order, from 0
     * @param string $dimension what declares the variants (the enum class, or the configuration key of a list): only
     *        variants of the same dimension compare
     */
    public function __construct(
        public UnitEnum|string $value,
        public string $name,
        public int $position,
        public string $dimension,
    ) {}

    /**
     * A variant of an enum case: its name is the value of a backed case, else the name of the case.
     */
    public static function ofCase(UnitEnum $case, int $position): self
    {
        return new self($case, $case instanceof BackedEnum ? (string) $case->value : $case->name, $position, $case::class);
    }

    /**
     * A variant declared as a string of a list, the list being declared at `$dimension` (a configuration key).
     */
    public static function ofString(string $value, int $position, string $dimension): self
    {
        return new self($value, $value, $position, $dimension);
    }

    /**
     * Whether this variant is `$variant` or older; a `LogicException` when the variants belong to two dimensions.
     */
    public function isAtMost(self $variant): bool
    {
        return $this->position <= $this->compared($variant)->position;
    }

    /**
     * Whether this variant is `$variant` or newer; a `LogicException` when the variants belong to two dimensions.
     */
    public function isAtLeast(self $variant): bool
    {
        return $this->position >= $this->compared($variant)->position;
    }

    /**
     * Whether this variant is `$variant`; a `LogicException` when the variants belong to two dimensions.
     */
    public function is(self $variant): bool
    {
        return $this->position === $this->compared($variant)->position;
    }

    /**
     * `$variant`, once checked to belong to the dimension of this variant.
     */
    private function compared(self $variant): self
    {
        return $variant->dimension === $this->dimension
            ? $variant
            : throw new LogicException("Variants of {$this->dimension} and {$variant->dimension} do not compare.");
    }
}
