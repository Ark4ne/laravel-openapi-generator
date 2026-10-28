<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Sampling;

use Ark4ne\OpenApi\Types\NamedType;
use Ark4ne\OpenApi\Types\TypeResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Finds what a resource receives: the model of a resource, the resource of a collection.
 */
final readonly class ResourceModels
{
    private const USE_RESOURCE = ['Illuminate\Database\Eloquent\Attributes\UseResource', 'Illuminate\Database\Eloquent\Attributes\UseResourceCollection'];

    private const COLLECTS = 'Illuminate\Http\Resources\Attributes\Collects';

    public function __construct(
        private TypeResolver $types,
    ) {}

    /**
     * In order: the type of the constructor, `@extends JsonResource<User>`, `@mixin`, `#[UseResource]` or
     * `#[UseResourceCollection]` of a loaded model, the naming convention (`…\Http\Resources\UserResource` →
     * `…\Models\User`, then `App\Models\User`).
     *
     * @param class-string $resource
     *
     * @return class-string<Model>|null
     */
    public function model(string $resource): ?string
    {
        $class = new ReflectionClass($resource);

        return $this->constructor($class)
            ?? self::modelOf($this->types->genericArguments($class, JsonResource::class)[0] ?? null)
            ?? $this->mixin($class)
            ?? self::declaredBy($resource)
            ?? self::convention($resource);
    }

    /**
     * In order: `#[Collects]`, `$collects`, `@extends ResourceCollection<UserResource>`, the convention of
     * Laravel (`UserCollection` → `User`, then `UserResource`).
     *
     * @param class-string $collection
     *
     * @return class-string<JsonResource>|null
     */
    public function collected(string $collection): ?string
    {
        $class = new ReflectionClass($collection);
        $attribute = $class->getAttributes(self::COLLECTS)[0] ?? null;
        $candidates = [
            $attribute?->getArguments()[0] ?? null,
            $class->hasProperty('collects') ? $class->getProperty('collects')->getDefaultValue() : null,
            ($argument = $this->types->genericArguments($class, ResourceCollection::class)[0] ?? null) instanceof NamedType ? $argument->name : null,
        ];

        if (str_ends_with($collection, 'Collection')) {
            $base = substr($collection, 0, -10);
            array_push($candidates, $base, "{$base}Resource");
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && class_exists($candidate) && is_subclass_of($candidate, JsonResource::class)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param ReflectionClass<object> $class
     *
     * @return class-string<Model>|null
     */
    private function constructor(ReflectionClass $class): ?string
    {
        $type = $class->getConstructor()?->getParameters()[0]?->getType();

        return $type instanceof ReflectionNamedType ? self::modelOf(new NamedType($type->getName())) : null;
    }

    /**
     * @param ReflectionClass<object> $class
     *
     * @return class-string<Model>|null
     */
    private function mixin(ReflectionClass $class): ?string
    {
        foreach ($this->types->docBlock($class)->node->getMixinTagValues() as $mixin) {
            $model = self::modelOf($this->types->parse((string) $mixin->type, $class));

            if ($model !== null) {
                return $model;
            }
        }

        return null;
    }

    /**
     * @return class-string<Model>|null
     */
    private static function declaredBy(string $resource): ?string
    {
        foreach (get_declared_classes() as $class) {
            if (! is_subclass_of($class, Model::class)) {
                continue;
            }

            foreach ((new ReflectionClass($class))->getAttributes() as $attribute) {
                if (in_array($attribute->getName(), self::USE_RESOURCE, true) && ($attribute->getArguments()[0] ?? null) === $resource) {
                    return $class;
                }
            }
        }

        return null;
    }

    /**
     * @return class-string<Model>|null
     */
    private static function convention(string $resource): ?string
    {
        $basename = substr($resource, (int) strrpos('\\' . $resource, '\\'));
        $name = (string) preg_replace('/(Resource|Collection)$/', '', $basename);
        $position = strpos($resource, '\Http\Resources\\');
        $candidates = $position === false ? [] : [substr($resource, 0, $position) . "\\Models\\{$name}"];
        $candidates[] = "App\\Models\\{$name}";

        foreach ($candidates as $candidate) {
            $model = self::modelOf(new NamedType($candidate));

            if ($model !== null) {
                return $model;
            }
        }

        return null;
    }

    /**
     * @return class-string<Model>|null
     */
    private static function modelOf(mixed $type): ?string
    {
        return $type instanceof NamedType && class_exists($type->name) && is_subclass_of($type->name, Model::class) ? $type->name : null;
    }
}
