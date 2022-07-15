<?php

namespace Ark4ne\OpenApi\Documentation\Request;

use Ark4ne\OpenApi\Contracts\OASSchematable;
use Ark4ne\OpenApi\Documentation\Request\Concerns\Typable;
use Ark4ne\OpenApi\Documentation\Request\Concerns\HasCondition;
use Ark4ne\OpenApi\Support\Date;
use DateTimeInterface;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Parameter as OASParameter;
use GoldSpecDigital\ObjectOrientedOAS\Objects\Schema;
use Illuminate\Support\Arr;

/**
 * @property-read array<int|string> $enum
 * @property-read string            $type
 * @property-read string            $name
 */
class Parameter implements OASSchematable
{
    use Typable, HasCondition;

    /** A JSON array. */
    public const TYPE_ARRAY = 'array';
    /** A JSON boolean. */
    public const TYPE_BOOLEAN = 'boolean';
    /** A JSON number without a fraction or exponent part. */
    public const TYPE_INTEGER = 'integer';
    /** Any JSON number. Number includes integer. */
    public const TYPE_NUMBER = 'number';
    /** A JSON object. */
    public const TYPE_OBJECT = 'object';
    /** A JSON string. */
    public const TYPE_STRING = 'string';

    /** int32    signed 32 bits */
    public const FORMAT_INTEGER = 'integer';
    /** int64    signed 64 bits */
    public const FORMAT_LONG = 'long';
    /** float */
    public const FORMAT_FLOAT = 'float';
    /** double */
    public const FORMAT_DOUBLE = 'double';
    /** string */
    public const FORMAT_STRING = 'string';
    /** byte    base64 encoded characters */
    public const FORMAT_BYTE = 'byte';
    /** binary    any sequence of octets */
    public const FORMAT_BINARY = 'binary';
    /** boolean */
    public const FORMAT_BOOLEAN = 'boolean';
    /** date    As defined by full-date - RFC3339 */
    public const FORMAT_DATE = 'date';
    /** date-time    As defined by date-time - RFC3339 */
    public const FORMAT_DATETIME = 'datetime';
    /** password    A hint to UIs to obscure input. */
    public const FORMAT_PASSWORD = 'password';
    /** uuid    */
    public const FORMAT_UUID = 'uuid';

    public const PATTERNS = [
        self::FORMAT_DATE => 'Y-m-d',
        self::FORMAT_DATETIME => DateTimeInterface::ATOM,
    ];

    protected const FORMATS = [
        self::TYPE_ARRAY => [],
        self::TYPE_BOOLEAN => [self::FORMAT_BOOLEAN],
        self::TYPE_INTEGER => [self::FORMAT_INTEGER, self::FORMAT_LONG],
        self::TYPE_NUMBER => [self::FORMAT_INTEGER, self::FORMAT_LONG, self::FORMAT_FLOAT, self::FORMAT_DOUBLE],
        self::TYPE_STRING => [
            self::FORMAT_STRING,
            self::FORMAT_BYTE,
            self::FORMAT_BINARY,
            self::FORMAT_DATE,
            self::FORMAT_DATETIME,
            self::FORMAT_PASSWORD,
            self::FORMAT_UUID
        ],
    ];

    protected string $type;
    protected ?string $format;

    protected bool $required = false;
    protected bool $nullable = false;
    protected mixed $default;
    /** @var array<string> */
    protected ?array $enum;

    protected ?string $pattern;

    protected null|int|float $multipleOf;

    protected null|int|float $min;
    protected null|int|float $max;

    protected ?bool $exclusiveMin;
    protected ?bool $exclusiveMax;

    /**
     * @var array<self>|null
     */
    protected ?array $properties;
    protected ?self $items;

    protected ?string $title;
    protected ?string $typeDescription;
    protected ?string $description;
    protected mixed $example;

    protected ?string $ref;

    protected bool $undotName = false;
    protected bool $flatName = false;

    public function __construct(
        protected string $name,
    ) {
        $this->type(self::TYPE_STRING);
    }

    public function __isset(string $name): bool
    {
        return isset($this->$name);
    }

    public function __get(string $name): mixed
    {
        return $this->$name ?? null;
    }

    public function title(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function typeDescription(string $description): static
    {
        $this->typeDescription = $description;
        return $this;
    }

    public function example(mixed $example): static
    {
        $this->example = $example;
        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;
        return $this;
    }

    public function nullable(bool $nullable = true): static
    {
        $this->nullable = $nullable;
        return $this;
    }

    public function default(bool $default = true): static
    {
        $this->default = $default;
        return $this;
    }

    /**
     * @param array<string> $enum
     *
     * @return $this
     */
    public function enum(array $enum): static
    {
        $this->enum = $enum;
        return $this;
    }

    public function pattern(string $pattern): static
    {
        $this->pattern = $pattern;
        return $this;
    }

    public function multipleOf(int|float $multipleOf): static
    {
        $this->multipleOf = $multipleOf;
        return $this;
    }

    public function min(int|float $min): static
    {
        $this->min = $min;
        return $this;
    }

    public function max(int|float $max): static
    {
        $this->max = $max;
        return $this;
    }

    public function exclusiveMin(bool $exclusive = true): static
    {
        $this->exclusiveMin = $exclusive;
        return $this;
    }

    public function exclusiveMax(bool $exclusive = true): static
    {
        $this->exclusiveMax = $exclusive;
        return $this;
    }

    public function properties(self ...$properties): static
    {
        $this->properties = $properties;
        return $this;
    }

    public function items(self $items): static
    {
        $this->items = $items;
        return $this;
    }

    public function ref(string $ref): static
    {
        $this->ref = $ref;
        return $this;
    }

    public function undot(bool $undot = true): static
    {
        $this->undotName = $undot;
        return $this;
    }

    public function flat(bool $flat = true): static
    {
        $this->flatName = $flat;
        return $this;
    }

    public function oasSchema(): Schema
    {
        $name = $this->getName();

        if($this->ref ?? null) {
            return Schema::ref($this->ref, $name);
        }

        /** @var Schema $schema */
        $schema = Schema::{$this->type}($name);

        $schema = $schema
            ->description($this->schemaDescription())
            ->example($this->example ?? null)
            ->nullable($this->nullable)
            ->format($this->format ?? null)
            ->enum(...($this->enum ?? []))
            ->pattern($this->pattern ?? null)
            ->default($this->default ?? null)
            ->properties(...array_map(fn(self $param) => $param->oasSchema(), $this->properties ?? []))
            ->multipleOf($this->multipleOf ?? null)// ->additionalProperties($additionalProperties)  // TODO
        ;

        if ($this->items ?? null) {
            $schema = $schema->items($this->items->oasSchema());
        }

        if (($this->min ?? null) && ($this->exclusiveMin ?? null)) {
            $schema = $schema->exclusiveMinimum($this->min);
        } elseif ($this->min ?? null) {
            $schema = $schema->minimum($this->min);
        }

        if (($this->max ?? null) && ($this->exclusiveMax ?? null)) {
            $schema = $schema->exclusiveMaximum($this->max);
        } elseif ($this->max ?? null) {
            $schema = $schema->maximum($this->max);
        }

        return $schema;
    }

    public function oasParameters(string $for): OASParameter
    {
        $schema = $this->oasSchema();

        /** @var OASParameter $params */
        $params = OASParameter::$for($schema->objectId);
        $params = $params
            ->name($this->getName())
            ->required($this->required)
            ->allowEmptyValue($this->nullable)
            ->schema($schema)
            ->example($this->example ?? null)
            ->description($this->description ?? null);

        return $params;
    }

    protected function getName(): string
    {
        if ($this->undotName) {
            return Arr::last(explode('.', $this->name));
        }
        if ($this->flatName) {
            return collect(explode('.', $this->name))
                ->map(fn($name) => $name === '*' ? '' : $name)
                ->reduce(
                    fn(string $name, string $sub) => $name ? "{$name}[$sub]" : $sub,
                    ''
                );
        }
        return $this->name;
    }

    protected function schemaDescription(): ?string
    {
        $more = [$this->typeDescription ?? null, ...$this->conditions];

        if (in_array($this->format ?? null, [self::FORMAT_DATE, self::FORMAT_DATETIME], true)) {
            $more[] = $this->dateSchemaDescription();
        }

        return implode("  \n", array_map('\strval', array_filter($more)));
    }

    protected function dateSchemaDescription(): ?string
    {
        foreach ([$this->pattern ?? null, self::PATTERNS[$this->format]] as $item) {
            if ($item && Date::isFormat($item)) {
                $format = $item;
                break;
            }
        }

        if (!isset($format)) {
            return null;
        }

        $min = $this->min ?? null;
        $max = $this->max ?? null;
        $exclusiveMin = $this->exclusiveMin ?? null;
        $exclusiveMax = $this->exclusiveMax ?? null;

        if ($min === $max) {
            $desc[] = '`=' . date($format, $min) . '`';
        } else {
            if ($min) {
                $desc[] = '`>' . ($exclusiveMin ? '' : '=') . ' ' . date($format, $min) . '`';
            }
            if ($max) {
                $desc[] = '`<' . ($exclusiveMax ? '' : '=') . ' ' . date($format, $max) . '`';
            }
        }

        return isset($desc) ? implode(' ', $desc) : null;
    }

    public static function fromJson(mixed $data, string $name = ''): self
    {
        $parameter = new self($name);

        if (is_null($data)) {
            $parameter->example($data);
        } elseif (is_bool($data)) {
            $parameter->bool()->example($data);
        } elseif (is_int($data)) {
            $parameter->int()->example($data);
        } elseif (is_float($data)) {
            $parameter->float()->example($data);
        } elseif (is_numeric($data)) {
            $parameter->number()->example($data);
        } elseif (is_string($data)) {
            $parameter->string()->example($data);
        } elseif (is_array($data)) {
            if (Arr::isAssoc($data)) {
                $parameter->object()->properties(...collect($data)->map(
                    fn($value, $key) => self::fromJson($value, $key)
                ));
            } elseif (!empty($data)) {
                $parameter->object()->properties(...collect($data)->map(
                    fn($value, $key) => self::fromJson($value, $key)
                ));
            } else {
                $parameter->array();
            }
        }

        return $parameter;
    }
}
