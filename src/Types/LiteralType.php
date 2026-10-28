<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Types;

/**
 * A constant value: `'draft'`, `1`, `true`, or a scalar class constant (`Status::DRAFT`) once resolved.
 */
final readonly class LiteralType implements Type
{
    public function __construct(
        public int|float|string|bool $value,
    ) {}

    public function toString(): string
    {
        return match (true) {
            is_string($this->value) => "'" . addcslashes($this->value, "'\\") . "'",
            is_bool($this->value) => $this->value ? 'true' : 'false',
            default => (string) $this->value,
        };
    }
}
