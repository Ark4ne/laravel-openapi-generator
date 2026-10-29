<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Types;

/**
 * A class, interface, enum or built-in type, without type arguments: `int`, `App\Models\User`.
 */
final readonly class NamedType implements Type
{
    /**
     * Built-in and PHPDoc pseudo types; any other name is a class name.
     */
    public const BUILTIN = [
        'int', 'float', 'string', 'bool', 'true', 'false', 'null', 'void', 'never', 'array', 'iterable', 'callable',
        'object', 'resource', 'scalar', 'numeric', 'array-key', 'positive-int', 'negative-int', 'non-positive-int',
        'non-negative-int', 'non-zero-int', 'non-empty-string', 'non-falsy-string', 'truthy-string', 'literal-string',
        'lowercase-string', 'uppercase-string', 'numeric-string', 'class-string', 'interface-string', 'enum-string',
        'trait-string', 'callable-string', 'non-empty-array', 'closed-resource', 'open-resource', 'min', 'max',
    ];

    /**
     * Synonyms written by some PHPDoc, normalised to their PHP name.
     */
    public const ALIASES = ['integer' => 'int', 'boolean' => 'bool', 'double' => 'float', 'real' => 'float'];

    /**
     * @param string $name a built-in name in lower case, or a fully qualified class name without leading `\`
     */
    public function __construct(
        public string $name,
    ) {}

    public function isBuiltin(): bool
    {
        return in_array($this->name, self::BUILTIN, true);
    }

    /**
     * Whether the name is an existing class, interface or enum.
     */
    public function isClass(): bool
    {
        return ! $this->isBuiltin() && (class_exists($this->name) || interface_exists($this->name));
    }

    public function toString(): string
    {
        return $this->name;
    }
}
