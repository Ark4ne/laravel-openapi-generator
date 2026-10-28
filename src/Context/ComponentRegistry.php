<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Context;

use Ark4ne\OpenApiWriter\Objects\Schema;

/**
 * Named schemas met during a generation (resources, enums), keyed by the class they describe.
 *
 * A name already taken by another class gets a suffix (`User2`) and a warning: two classes never share a component.
 */
final class ComponentRegistry
{
    /**
     * Registers the schema of a class and returns the component name it was given.
     *
     * @param class-string $source
     * @param string $name preferred name (`#[SchemaName]`, class basename)
     */
    public function register(string $source, string $name, Schema $schema): string
    {
        return $name;
    }

    /**
     * @param class-string $source
     */
    public function nameOf(string $source): ?string
    {
        return null;
    }

    public function has(string $name): bool
    {
        return false;
    }

    public function get(string $name): ?Schema
    {
        return null;
    }

    /**
     * @return array<string, Schema> component name → schema, in registration order
     */
    public function all(): array
    {
        return [];
    }
}
