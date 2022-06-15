<?php

namespace Ark4ne\OpenApi\Support\Reflection;

use Ark4ne\OpenApi\Support\Reflection;

/**
 * @template T
 * @template U
 */
class Type
{
    /**
     * @param class-string<T>|null    $type
     * @param bool|null $builtin
     * @param bool      $generic
     * @param class-string<U>|null    $sub
     */
    public function __construct(
        protected ?string $type = null,
        protected ?bool $builtin = null,
        protected bool $generic = false,
        protected ?string $sub = null
    ) {
    }

    /**
     * @param class-string<T> $type
     *
     * @return $this
     */
    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function builtin(bool $builtin): self
    {
        $this->builtin = $builtin;
        return $this;
    }

    public function generic(bool $generic): self
    {
        $this->generic = $generic;
        return $this;
    }

    /**
     * @param class-string<U> $sub
     *
     * @return $this
     */
    public function sub(string $sub): self
    {
        $this->sub = $sub;
        return $this;
    }

    /**
     * @return class-string<T>|null
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function isBuiltin(): bool
    {
        return $this->builtin ??= Reflection::isBuiltin($this->type);
    }

    /**
     * @return bool
     */
    public function isGeneric(): bool
    {
        return $this->generic;
    }

    /**
     * @return class-string<U>|null
     */
    public function getSub(): ?string
    {
        return $this->sub;
    }

    /**
     * @return bool
     */
    public function isInstantiable(): bool
    {
        return !$this->isBuiltin();
    }

    public static function make(
        ?string $type = null,
        ?bool $builtin = null,
        bool $generic = false,
        ?string $sub = null
    ): self {
        return new self($type, $builtin, $generic, $sub);
    }
}
