<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Types;

/**
 * A type with type arguments: `Collection<int, App\User>`, `array<string, int>`, `class-string<App\User>`.
 */
final readonly class GenericType implements Type
{
    /**
     * @param string $name built-in name or fully qualified class name
     * @param non-empty-list<Type> $arguments
     */
    public function __construct(
        public string $name,
        public array $arguments,
    ) {}

    public function base(): NamedType
    {
        return new NamedType($this->name);
    }

    public function toString(): string
    {
        return $this->name . '<' . implode(', ', array_map(static fn(Type $type): string => $type->toString(), $this->arguments)) . '>';
    }
}
