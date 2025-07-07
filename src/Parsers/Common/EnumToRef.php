<?php

namespace Ark4ne\OpenApi\Parsers\Common;

use Ark4ne\OpenApi\Documentation\Request\Component;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Support\ArrayCache;
use Ark4ne\OpenApi\Support\Ref;
use Illuminate\Support\Str;

class EnumToRef
{
    public function __construct(private string|\UnitEnum|\BackedEnum $enum)
    {
        ArrayCache::fetch([self::class, $this->enumClass(), 'values'], fn() => []);
        ArrayCache::fetch([self::class, $this->enumClass(), 'ref'], fn() => null);
    }

    private function enumClass(): string
    {
        return is_string($this->enum) ? $this->enum : $this->enum::class;
    }

    public function toRef(): string
    {
        $ref = Ref::enumRef($this->enumClass());

        if (Component::has($ref, Component::SCOPE_SCHEMAS)) {
            return Component::get($ref, Component::SCOPE_SCHEMAS)?->ref();
        }

        $param = $this->applyOnParameter(new Parameter($ref));

        $component = Component::create($ref, Component::SCOPE_SCHEMAS);
        $component->object($param);

        return ArrayCache::set([self::class, $this->enumClass(), 'ref'], $component->ref());
    }

    public function applyOnParameter(Parameter $parameter): Parameter
    {
        $parameter
            ->title(Str::studly($this->enumClass()))
            ->description('enum')
            ->string()
            ->enum($this->describeEnumValues($this->enum));

        return $parameter;
    }

    private function describeEnumValues(string|\UnitEnum|\BackedEnum $enum)
    {
        $values = array_map(
            fn($case) => $case instanceof \BackedEnum
                ? $case->value
                : $case->name,
            $enum::cases()
        );

        ArrayCache::set([self::class, $this->enumClass(), 'values'], $values);

        return $values;
    }

    public static function fromValues(array $values): string
    {
        $discovered = ArrayCache::get([self::class]);

        foreach ($discovered ?? [] as $enum => $cases) {
            if (array_diff($values, $cases['values']) === [] && array_diff($cases['values'], $values) === []) {
                return $cases['ref'];
            }
        }

        $keys = md5(json_encode($values));

        $ref = Ref::enumRef($keys);

        if (Component::has($ref, Component::SCOPE_SCHEMAS)) {
            return Component::get($ref, Component::SCOPE_SCHEMAS)?->ref();
        }

        $parameter = (new Parameter($ref))
            ->title(Str::studly($keys))
            ->description('enum')
            ->string()
            ->enum($values);

        $component = Component::create($ref, Component::SCOPE_SCHEMAS);
        $component->object($parameter);

        ArrayCache::set([self::class, $keys, 'values'], $values);

        return ArrayCache::set([self::class, $keys, 'ref'], $component->ref());
    }
}
