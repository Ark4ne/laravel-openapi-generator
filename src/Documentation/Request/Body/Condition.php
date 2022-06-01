<?php

namespace Ark4ne\OpenApi\Documentation\Request\Body;

class Condition
{
    public const TYPE_IF = 'if';
    public const TYPE_UNLESS = 'unless';

    public const TYPE_WITH = 'with';
    public const TYPE_WITHOUT = 'without';

    public const TYPE_WITH_ALL = 'with-all';
    public const TYPE_WITHOUT_ALL = 'without-all';

    /**
     * @param string                   $type
     * @param null|string              $attrs
     * @param null|string|array<mixed> $value
     * @param null|string              $rule
     */
    public function __construct(
        protected string $type,
        protected null|string $attrs,
        protected null|string|array $value = null,
        protected null|string $rule = null,
    ) {
    }

    public function __toString()
    {
        $str = [];

        if ($this->rule) {
            $str[] = "**$this->rule**";
        }

        if ($this->type) {
            $str[] = "**{$this->typeReadable()}**";
        }

        if ($this->attrs) {
            $str[] = "`$this->attrs`";
        }

        if ($this->value) {
            $str[] = $this->checker();

            $str[] = '`' . implode('`, `', (array)$this->value) . '`';
        }

        return ucfirst(implode(' ', $str));
    }

    protected function typeReadable(): string
    {
        return match ($this->type) {
            self::TYPE_WITH_ALL => self::TYPE_WITH,
            self::TYPE_WITHOUT_ALL => self::TYPE_WITHOUT,
            default => $this->type,
        };
    }

    protected function checker(): string
    {
        return match ($this->type) {
            self::TYPE_IF, self::TYPE_UNLESS => 'in',
            self::TYPE_WITH, self::TYPE_WITHOUT => 'one of',
            self::TYPE_WITH_ALL, self::TYPE_WITHOUT_ALL => 'all of',
            default => '',
        };
    }
}
