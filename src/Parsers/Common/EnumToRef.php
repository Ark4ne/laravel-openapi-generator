<?php

namespace Ark4ne\OpenApi\Parsers\Common;

use Ark4ne\OpenApi\Documentation\Request\Component;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Illuminate\Support\Str;

class EnumToRef
{
    public function __construct(private string|\UnitEnum|\BackedEnum $enum)
    {
    }

    public function toRef(): string
    {
        $ref = "enum-" . Str::slug(str_replace('\\', '-', (is_string($this->enum) ? $this->enum : $this->enum::class)));

        if (Component::has($ref, Component::SCOPE_SCHEMAS)) {
            return Component::get($ref, Component::SCOPE_SCHEMAS)?->ref();
        }

        $param = (new Parameter($ref))
            ->string()
            ->enum($this->describeEnumValues($this->enum));

        $component = Component::create($ref, Component::SCOPE_SCHEMAS);
        $component->object($param);

        return $component->ref();
    }

    private function describeEnumValues(string|\UnitEnum|\BackedEnum $enum)
    {
        $values = array_map(
            fn($case) => $case instanceof \BackedEnum
                ? $case->value
                : $case->name,
            $enum::cases()
        );

        return $values;
    }
}
