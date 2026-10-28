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
    /** @var array<string, Schema> component name → schema, in registration order */
    private array $schemas = [];

    /** @var array<class-string, string> class → component name */
    private array $names = [];

    public function __construct(
        private readonly ?Diagnostics $diagnostics = null,
    ) {}

    /**
     * Registers the schema of a class and returns the component name it was given.
     *
     * @param class-string $source
     * @param string $name preferred name (`#[SchemaName]`, class basename)
     */
    public function register(string $source, string $name, Schema $schema): string
    {
        $given = $this->names[$source] ?? $this->free($source, $name);
        $this->names[$source] = $given;
        $this->schemas[$given] = $schema->objectId($given);

        return $given;
    }

    /**
     * @param class-string $source
     */
    public function nameOf(string $source): ?string
    {
        return $this->names[$source] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->schemas[$name]);
    }

    public function get(string $name): ?Schema
    {
        return $this->schemas[$name] ?? null;
    }

    /**
     * @return array<string, Schema> component name → schema, in registration order
     */
    public function all(): array
    {
        return $this->schemas;
    }

    /**
     * @param class-string $source
     */
    private function free(string $source, string $name): string
    {
        if (! $this->has($name)) {
            return $name;
        }

        $suffix = 2;

        while ($this->has($name . $suffix)) {
            $suffix++;
        }

        $owner = array_search($name, $this->names, true);
        $this->diagnostics?->warning(Stage::Pass, sprintf('component %s already describes %s, %s is named %s', $name, is_string($owner) ? $owner : '?', $source, $name . $suffix));

        return $name . $suffix;
    }
}
