<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Requests;

use Ark4ne\OpenApi\Attributes\Cookie;
use Ark4ne\OpenApi\Attributes\Header;
use Ark4ne\OpenApi\Attributes\PathParameter;
use Ark4ne\OpenApi\Attributes\QueryParameter;
use Ark4ne\OpenApi\Attributes\RequestBody;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\BodyDescription;
use Ark4ne\OpenApi\Description\ParameterDescription;
use Ark4ne\OpenApi\Description\RequestDescription;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Extraction\Responses\ResourceSchemas;
use Ark4ne\OpenApi\Sampling\TypeSchemas;
use Ark4ne\OpenApi\Types\TypeResolver;
use Ark4ne\OpenApiWriter\Objects\Schema;
use Illuminate\Http\Resources\Json\JsonResource;
use ReflectionClass;
use ReflectionFunctionAbstract;
use Throwable;

/**
 * `#[QueryParameter]`, `#[Header]`, `#[Cookie]` on the controller, the request class or the action (each completing
 * the parameter of the same name and location given by the rules or by the one before),
 * `#[PathParameter]` and `#[RequestBody]` on the action. Types are PHPDoc types, read in the scope of the class or
 * of the action that carries the attribute.
 */
final readonly class AttributeRequestExtractor implements RequestExtractor
{
    public const PRIORITY = 300;

    /**
     * @param ResourceSchemas|null $resources describes `#[RequestBody(resource:)]`; without it, an info
     */
    public function __construct(
        private TypeResolver $types,
        private ?ResourceSchemas $resources = null,
    ) {}

    public function priority(): int
    {
        return self::PRIORITY;
    }

    public function supports(OperationContext $operation): bool
    {
        return $operation->action !== null;
    }

    public function extract(OperationContext $operation, RequestDescription $request, GenerationContext $context): RequestDescription
    {
        $action = $operation->action;

        if ($action === null) {
            return $request;
        }

        $class = RequestClass::of($operation);
        $reflection = $class === null ? null : new ReflectionClass($class);

        foreach ([QueryParameter::class, Header::class, Cookie::class] as $attribute) {
            foreach ($this->declared($attribute, $reflection, $operation, $context) as [$instance, $scope]) {
                $request = $request->parameters(...FieldPlacer::complete($request->parameters, $this->parameter($instance, $scope, $request->parameters)));
            }
        }

        foreach ($operation->declaredActionAttributes(PathParameter::class, $context) as [$path, $declaration]) {
            $request = $request->parameters(...FieldPlacer::merge($request->parameters, [$this->path($path, $request, $declaration)]));
        }

        foreach ($operation->declaredActionAttributes(RequestBody::class, $context) as [$body, $declaration]) {
            $request = $this->body($body, $request, $declaration, $operation, $context);
        }

        return $request;
    }

    /**
     * The parameter of the attribute, completing the one of the same name and location already described: `type`
     * replaces its schema, a `null` value keeps what it had.
     *
     * @param ReflectionClass<covariant object>|ReflectionFunctionAbstract $scope
     * @param list<ParameterDescription> $parameters
     */
    private function parameter(QueryParameter|Header|Cookie $attribute, ReflectionClass|ReflectionFunctionAbstract $scope, array $parameters): ParameterDescription
    {
        $parameter = match (true) {
            $attribute instanceof QueryParameter => ParameterDescription::query($attribute->name),
            $attribute instanceof Header => ParameterDescription::header($attribute->name),
            default => ParameterDescription::cookie($attribute->name),
        };
        $parameter = FieldPlacer::find($parameter, $parameters) ?? $parameter;
        $schema = $attribute->type === null ? $parameter->schema : $this->schema($attribute->type, $scope);
        $parameter = $parameter
            ->schema($attribute->example === null ? $schema : $schema->example($attribute->example))
            ->description($attribute->description ?? $parameter->description);
        $parameter = $attribute->required === null ? $parameter : $parameter->required($attribute->required);

        return $attribute instanceof QueryParameter && $attribute->deprecated !== null ? $parameter->deprecated($attribute->deprecated) : $parameter;
    }

    /**
     * The attributes of the controller, then of the request class, then of the action, each with the scope its
     * PHPDoc types are written in.
     *
     * @template T of QueryParameter|Header|Cookie
     *
     * @param class-string<T> $attribute
     * @param ReflectionClass<covariant object>|null $request
     *
     * @return list<array{T, ReflectionClass<covariant object>|ReflectionFunctionAbstract}>
     */
    private function declared(string $attribute, ?ReflectionClass $request, OperationContext $operation, GenerationContext $context): array
    {
        $declared = $operation->declaredClassAttributes($attribute, $context);

        if ($request !== null) {
            foreach ($this->instances($request, $attribute, $operation, $context) as $instance) {
                $declared[] = [$instance, $request];
            }
        }

        return [...$declared, ...$operation->declaredActionAttributes($attribute, $context)];
    }

    private function path(PathParameter $attribute, RequestDescription $request, ReflectionFunctionAbstract $action): ParameterDescription
    {
        $existing = ParameterDescription::path($attribute->name);
        $existing = FieldPlacer::find($existing, $request->parameters) ?? $existing;

        $schema = $attribute->type === null ? $existing->schema : $this->schema($attribute->type, $action);
        $schema = $attribute->example === null ? $schema : $schema->example($attribute->example);

        return $existing->schema($schema)->description($attribute->description ?? $existing->description);
    }

    private function body(RequestBody $attribute, RequestDescription $request, ReflectionFunctionAbstract $action, OperationContext $operation, GenerationContext $context): RequestDescription
    {
        $resource = $attribute->type === null ? $attribute->resource : null;

        if ($resource !== null && ($this->resources === null || ! is_subclass_of($resource, JsonResource::class))) {
            $context->diagnostics->info(Stage::Request, "#[RequestBody(resource:)] needs a resource, {$resource} is not one: body left out", $operation->route->label());

            return $request;
        }

        $body = $request->body;
        $schema = match (true) {
            $resource !== null => $this->resources->request($resource, $context),
            $attribute->type !== null => $this->schema($attribute->type, $action),
            default => null,
        };

        if ($schema !== null) {
            $body = ($body ?? BodyDescription::create())
                ->content($attribute->mediaType ?? ($resource === null ? FieldPlacer::JSON : $this->resources->mediaType($resource)), $schema)
                ->required($attribute->required ?? true);
        } elseif ($body !== null && $attribute->required !== null) {
            $body = $body->required($attribute->required);
        }

        // Without type, the attribute describes the body of the rules, if there is one.
        return $body === null || $attribute->description === null ? $request->body($body) : $request->body($body->description($attribute->description));
    }

    /**
     * @param ReflectionClass<covariant object>|ReflectionFunctionAbstract $scope
     */
    private function schema(string $type, ReflectionClass|ReflectionFunctionAbstract $scope): Schema
    {
        return TypeSchemas::of($this->types->parse($type, $scope));
    }

    /**
     * Attributes of the request class; one that cannot be built is left out with a warning.
     *
     * @template T of object
     *
     * @param ReflectionClass<covariant object> $class
     * @param class-string<T> $attribute
     *
     * @return list<T>
     */
    private function instances(ReflectionClass $class, string $attribute, OperationContext $operation, GenerationContext $context): array
    {
        $instances = [];

        foreach ($class->getAttributes($attribute) as $reflection) {
            try {
                $instances[] = $reflection->newInstance();
            } catch (Throwable $error) {
                $context->diagnostics->warning(Stage::Request, "invalid #[{$attribute}] on {$class->getName()}, attribute ignored: {$error->getMessage()}", $operation->route->label());
            }
        }

        return $instances;
    }
}
