<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Description;

use Ark4ne\OpenApi\Configuration\Variant;
use Ark4ne\OpenApi\Configuration\VariantsConfiguration;
use BackedEnum;
use UnitEnum;

/**
 * The first and the last variants that have an operation, both inclusive, by name: `#[Since]` and `#[Until]`, or an
 * integration that knows when its resource exists. Read only in a document with `variants`.
 */
final readonly class VariantBounds extends Description
{
    /**
     * @param string|null $since the name of the first variant that has the operation, `null` from the first one
     * @param string|null $until the name of the last variant that has the operation, `null` up to the head
     */
    protected function __construct(
        public ?string $since = null,
        public ?string $until = null,
    ) {}

    /**
     * Bounds given as enum cases (the value of a backed case, the name of a pure case) or as variant names.
     */
    public static function between(UnitEnum|string|null $since = null, UnitEnum|string|null $until = null): self
    {
        return new self(self::name($since), self::name($until));
    }

    public function since(UnitEnum|string|null $since): static
    {
        return $this->copy(since: self::name($since));
    }

    public function until(UnitEnum|string|null $until): static
    {
        return $this->copy(until: self::name($until));
    }

    /**
     * Whether the document of `$variant` has the operation: `$variant` is `since` or newer and `until` or older. The
     * head (`null`) is covered by bounds without `until`. A bound `$variants` does not name bounds nothing (see
     * `unknown()`).
     */
    public function covers(?Variant $variant, VariantsConfiguration $variants): bool
    {
        $since = $this->since === null ? null : $variants->named($this->since);
        $until = $this->until === null ? null : $variants->named($this->until);

        if ($variant === null) {
            return $until === null;
        }

        return ($since === null || $variant->isAtLeast($since)) && ($until === null || $variant->isAtMost($until));
    }

    /**
     * The bounds that name no variant of `$variants`, `since` first.
     *
     * @return list<string>
     */
    public function unknown(VariantsConfiguration $variants): array
    {
        $bounds = array_filter([$this->since, $this->until], static fn(?string $name): bool => $name !== null && $variants->named($name) === null);

        return array_values($bounds);
    }

    /**
     * The name of a variant given as an enum case: the value of a backed case, the name of a pure case.
     */
    private static function name(UnitEnum|string|null $variant): ?string
    {
        return match (true) {
            $variant instanceof BackedEnum => (string) $variant->value,
            $variant instanceof UnitEnum => $variant->name,
            default => $variant,
        };
    }
}
