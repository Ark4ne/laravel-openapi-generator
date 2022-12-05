<?php

namespace Ark4ne\OpenApi\Documentation\Request;

use Ark4ne\OpenApi\Contracts\OASSchematable;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Components;

class Component
{
    public const SCOPE_SCHEMAS = 'schemas';
    public const SCOPE_RESPONSES = 'responses';
    public const SCOPE_PARAMETERS = 'parameters';
    public const SCOPE_EXAMPLES = 'examples';
    public const SCOPE_REQUEST_BODIES = 'requestBodies';
    public const SCOPE_HEADERS = 'headers';
    public const SCOPE_SECURITY_SCHEMES = 'securitySchemes';
    public const SCOPE_LINKS = 'links';
    public const SCOPE_CALLBACKS = 'callbacks';

    /**
     * @var array<string, self[]>
     */
    private static array $refs = [];

    protected OASSchematable $object;

    private function __construct(
        protected string $id,
        protected string $scope = self::SCOPE_SCHEMAS
    ) {
    }

    public static function create(string $id, string $scope = self::SCOPE_SCHEMAS): self
    {
        if (isset(self::$refs[$scope][$id])) {
            throw new \InvalidArgumentException('Already exists');
        }

        return self::$refs[$scope][$id] = new self($id, $scope);
    }

    public static function has(string $id, string $scope = self::SCOPE_SCHEMAS): bool
    {
        return isset(self::$refs[$scope][$id]);
    }

    public static function get(string $id, string $scope = self::SCOPE_SCHEMAS): ?self
    {
        return self::$refs[$scope][$id] ?? null;
    }

    public function ref(): string
    {
        return "#/components/$this->scope/$this->id";
    }

    public function object(OASSchematable $object): self
    {
        $this->object = $object;

        return $this;
    }

    public static function toComponents(): ?Components
    {
        if (empty(self::$refs)) {
            return null;
        }

        $components = Components::create();

        foreach (self::$refs as $scope => $sub) {
            $components = $components->$scope(...array_map(
                static fn(self $component) => $component->object->oasSchema(),
                array_values($sub)
            ));
        }

        return $components;
    }
}
