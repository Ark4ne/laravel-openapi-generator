<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Description;

/**
 * One security requirement: every scheme it lists applies together. Several requirements are alternatives.
 */
final readonly class SecurityDescription extends Description
{
    /**
     * @param array<string, list<string>> $schemes scheme name → required scopes
     */
    protected function __construct(
        public array $schemes,
    ) {}

    /**
     * @param array<string, list<string>> $schemes `['bearer' => []]`; `[]` makes the authentication optional
     */
    public static function create(array $schemes = []): self
    {
        return new self($schemes);
    }

    /**
     * @param array<string, list<string>> $schemes
     */
    public function schemes(array $schemes): static
    {
        return $this->copy(schemes: $schemes);
    }

    /**
     * @param array<string, list<string>> $schemes
     */
    public function addSchemes(array $schemes): static
    {
        return $this->copy(schemes: [...$this->schemes, ...$schemes]);
    }
}
