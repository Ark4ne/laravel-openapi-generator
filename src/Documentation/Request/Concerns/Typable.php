<?php

namespace Ark4ne\OpenApi\Documentation\Request\Concerns;

use InvalidArgumentException;
use Throwable;

trait Typable
{
    public function type(string $type, $force = true): static
    {
        if (!$force && isset($this->type)) {
            return $this;
        }

        $this->type = $type;

        if (isset($this->format)) {
            try {
                $this->format($this->format, $force);
            } catch (Throwable $_) {
                unset($this->format);
            }
        }
        if (!isset($this->format) && isset(self::FORMATS[$this->type][0])) {
            $this->format = self::FORMATS[$this->type][0];
        }

        return $this;
    }

    public function format(string $format, bool $ensure = true): static
    {
        if (!in_array($format, self::FORMATS[$this->type], true)) {
            if ($ensure) {
                throw new InvalidArgumentException("format: '$format' not available for type: '$this->type'.");
            }

            return $this;
        }

        $this->format = $format;

        return $this;
    }

    public function array(bool $force = true): static
    {
        return $this->type(self::TYPE_ARRAY, $force);
    }

    public function bool(bool $force = true): static
    {
        return $this->type(self::TYPE_BOOLEAN, $force);
    }

    public function int(bool $force = true): static
    {
        return $this->type(self::TYPE_INTEGER, $force);
    }

    public function long(bool $force = true): static
    {
        return $this->type(self::TYPE_INTEGER, $force)->format(self::FORMAT_LONG, $force);
    }

    public function number(bool $force = true): static
    {
        return $this->type(self::TYPE_NUMBER, $force);
    }

    public function float(bool $force = true): static
    {
        return $this->type(self::TYPE_NUMBER, $force)->format(self::FORMAT_FLOAT, $force);
    }

    public function double(bool $force = true): static
    {
        return $this->type(self::TYPE_NUMBER, $force)->format(self::FORMAT_DOUBLE, $force);
    }

    public function object(bool $force = true): static
    {
        return $this->type(self::TYPE_OBJECT, $force);
    }

    public function string(bool $force = true): static
    {
        return $this->type(self::TYPE_STRING, $force);
    }

    public function byte(bool $force = true): static
    {
        return $this->type(self::TYPE_STRING, $force)->format(self::FORMAT_BYTE, $force);
    }

    public function binary(bool $force = true): static
    {
        return $this->type(self::TYPE_STRING, $force)->format(self::FORMAT_BINARY, $force);
    }

    public function date(bool $force = true): static
    {
        return $this
            ->type(self::TYPE_STRING, $force)
            ->format(self::FORMAT_DATE, $force);
    }

    public function dateTime(bool $force = true): static
    {
        return $this
            ->type(self::TYPE_STRING, $force)
            ->format(self::FORMAT_DATETIME, $force);
    }

    public function password(bool $force = true): static
    {
        return $this->type(self::TYPE_STRING, $force)->format(self::FORMAT_PASSWORD, $force);
    }

    public function uuid(bool $force = true): static
    {
        return $this->type(self::TYPE_STRING, $force)->format(self::FORMAT_UUID, $force);
    }
}
