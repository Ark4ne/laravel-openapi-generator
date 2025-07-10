<?php

namespace Ark4ne\OpenApi\Parsers\Common;

use Ark4ne\OpenApi\Documentation\Request\Component;
use Ark4ne\OpenApi\Documentation\Request\Parameter;
use Ark4ne\OpenApi\Support\ArrayCache;
use Ark4ne\OpenApi\Support\Ref;
use Ark4ne\OpenApi\Support\Reflection;
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
            ->string()
            ->enum($this->describeEnumValues($this->enum))
            ->x('type', 'enum')
            ->x('name', Str::studly(Reflection::reflection($this->enum)->getShortName()));;

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

    public static function fromValues(array $values, null|string $name = null): string
    {
        $discovered = ArrayCache::get([self::class]);

        foreach ($discovered ?? [] as $enum => $cases) {
            if (array_diff($values, $cases['values']) === [] && array_diff($cases['values'], $values) === []) {
                return $cases['ref'];
            }
        }

        $strKeys = Str::pascal(implode('-', $values));

        if (strlen($strKeys) <= 32) {
            $key = $strKeys;
        } else {
            $key = Str::pascal(Str::slug($name, '-')) . substr(md5($strKeys), 0, 6);
        }

        $ref = Ref::enumRef($key);

        if (Component::has($ref, Component::SCOPE_SCHEMAS)) {
            return Component::get($ref, Component::SCOPE_SCHEMAS)?->ref();
        }

        $parameter = (new Parameter($ref))
            ->string()
            ->enum($values)
            ->x('type', 'enum')
            ->x('name', Str::studly($key));

        $component = Component::create($ref, Component::SCOPE_SCHEMAS);
        $component->object($parameter);

        ArrayCache::set([self::class, $key, 'values'], $values);

        return ArrayCache::set([self::class, $key, 'ref'], $component->ref());
    }
}
