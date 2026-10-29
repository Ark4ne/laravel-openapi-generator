<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Types;

/**
 * Where a PHPDoc type is written: what its names resolve against.
 */
final readonly class TypeScope
{
    /**
     * @param array<string, string> $uses lower-cased alias → fully qualified name
     * @param class-string|null $self class of `self`, `static` and `$this`
     * @param class-string|null $parent
     * @param array<string, Type> $templates template name → its bound, or its type argument once known
     */
    public function __construct(
        public string $namespace = '',
        public array $uses = [],
        public ?string $self = null,
        public ?string $parent = null,
        public array $templates = [],
    ) {}

    /**
     * @param array<string, Type> $templates
     */
    public function withTemplates(array $templates): self
    {
        return new self($this->namespace, $this->uses, $this->self, $this->parent, [...$this->templates, ...$templates]);
    }

    /**
     * Fully qualified name of a class name written in this scope.
     */
    public function resolveClass(string $name): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $parts = explode('\\', $name, 2);
        $first = strtolower($parts[0]);

        if (isset($this->uses[$first])) {
            return $this->uses[$first] . (isset($parts[1]) ? '\\' . $parts[1] : '');
        }

        return $this->namespace === '' ? $name : "{$this->namespace}\\{$name}";
    }
}
