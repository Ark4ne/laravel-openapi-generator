<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Extraction\Responses;

use Ark4ne\OpenApi\Attributes\Response;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\ResponseDescription;
use Ark4ne\OpenApi\Description\ResponseOrigin;
use Ark4ne\OpenApi\Extraction\Exceptions\ErrorBodies;
use Ark4ne\OpenApi\Extraction\Exceptions\ExceptionMappers;
use Ark4ne\OpenApi\Extraction\Exceptions\ExceptionResponses;
use Ark4ne\OpenApi\Extraction\Exceptions\LaravelErrorBodies;
use Ark4ne\OpenApi\Extraction\OperationContext;
use Ark4ne\OpenApi\Sampling\ModelSchemas;
use Ark4ne\OpenApi\Sampling\RelationLoader;
use Ark4ne\OpenApi\Sampling\ResourceModels;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Sampling\SchemaInferrer;
use Ark4ne\OpenApi\Types\TypeResolver;
use Ark4ne\OpenApiWriter\Objects\Discriminator;
use Ark4ne\OpenApiWriter\Objects\Schema;
use ReflectionFunctionAbstract;

/**
 * Every response of an operation: inferred from each member of the return type, replaced by `#[Response]` for its
 * status, then those of the declared exceptions (merged with the other error responses in E7). A declared success
 * (2xx) also leaves out the successes inferred for other statuses.
 *
 * Several members with one status (`CatResource|DogResource`) give one response whose body is `oneOf` them, with a
 * `discriminator` when a common property takes a different value in each sample.
 */
final readonly class OperationResponses
{
    public function __construct(
        private ResponseExtractors $extractors,
        private DeclaredResponses $declared,
        private ExceptionResponses $exceptions,
        private ResourceSchemas $resources,
    ) {}

    /**
     * @param list<ResourceFormat> $formats
     * @param list<ResponseExtractor> $extractors joining the built-in ones by priority
     */
    public static function default(Samplers $samplers, TypeResolver $types, array $formats = [], ErrorBodies $errors = new LaravelErrorBodies(), array $extractors = []): self
    {
        $models = new ResourceModels($types);
        $resources = new ResourceSchemas($models, $samplers, new SchemaInferrer(new ModelSchemas($types)), new RelationLoader($samplers), $formats);
        $inferrer = $resources->inferrer();
        $values = new ValueSchemas($resources, $samplers, $inferrer);
        $registry = ResponseExtractors::default($resources, $models, $values, $samplers, $inferrer)->with(...$extractors);
        $declared = new DeclaredResponses($resources, new ResourceCollectionExtractor($resources, $models), $types);

        return new self($registry, $declared, new ExceptionResponses(ExceptionMappers::default($declared, $errors)), $resources);
    }

    /**
     * The `#[Response]` reader, shared with the callbacks (`#[Callback(resource:, type:)]`).
     */
    public function declared(): DeclaredResponses
    {
        return $this->declared;
    }

    /**
     * The resources this object describes, shared with the request bodies (`#[RequestBody(resource:)]`).
     */
    public function resources(): ResourceSchemas
    {
        return $this->resources;
    }

    /**
     * @return list<ResponseDescription>
     */
    public function describe(OperationContext $operation, GenerationContext $context): array
    {
        if ($operation->returnType === null && $operation->actionAttributes(Response::class, $context) === []) {
            $context->diagnostics->info(Stage::Response, 'no return type, declare the responses with #[Response]', $operation->route->label());
        }

        /** @var array<int|string, non-empty-list<ResponseDescription>> $byStatus */
        $byStatus = [];

        foreach ($operation->returnTypes() as $type) {
            foreach ($this->extractors->extract($type, $operation, $context) as $response) {
                $byStatus[$response->status][] = $response;
            }
        }

        $responses = array_map(fn(array $responses): ResponseDescription => $this->merge($responses, $context), $byStatus);

        $entries = $this->declared->describe($operation, $context);
        $declaredStatuses = array_map(static fn(array $entry): int|string => $entry[0]->status, $entries);

        // A declared success describes the successes: those inferred for other statuses are left out (a
        // `JsonResponse` built with 201 is inferred as a 200).
        if (array_filter($declaredStatuses, self::success(...)) !== []) {
            $responses = array_filter($responses, static fn(ResponseDescription $response): bool => ! self::success($response->status) || in_array($response->status, $declaredStatuses, true));
        }

        /** @var array<int|string, ReflectionFunctionAbstract> $declarations status → declaration of its last declared body */
        $declarations = [];

        // From the parent declaration down: a body replaces the one of a parent, adds up with one of the same
        // declaration (`oneOf`); a response without body changes only the description, the summary and the headers.
        foreach ($entries as [$declared, $declaration]) {
            $status = $declared->status;
            $previous = $responses[$status] ?? null;
            $hasBody = $declared->contents !== [] || $declared->itemContents !== [];

            if ($previous === null || ($hasBody && ($declarations[$status] ?? null) !== $declaration)) {
                $responses[$status] = $declared;
            } elseif ($hasBody) {
                $responses[$status] = $this->merge([$previous, $declared], $context)->description($declared->description)->summary($declared->summary)->addHeaders(...$declared->headers);
            } else {
                $responses[$status] = $previous->description($declared->description)->summary($declared->summary ?? $previous->summary)->addHeaders(...$declared->headers)->origin(ResponseOrigin::Declared);
            }

            if ($hasBody) {
                $declarations[$status] = $declaration;
            }
        }

        return [...array_values($responses), ...$this->exceptions->describe($operation, $context)];
    }

    private static function success(int|string $status): bool
    {
        return is_int($status) && $status >= 200 && $status < 300;
    }

    /**
     * @param non-empty-list<ResponseDescription> $responses of one status
     */
    private function merge(array $responses, GenerationContext $context): ResponseDescription
    {
        $merged = $responses[0];

        if (count($responses) === 1) {
            return $merged;
        }

        $schemas = [];

        foreach ($responses as $response) {
            foreach ($response->contents as $mediaType => $schema) {
                $schemas[$mediaType][] = $schema;
            }
        }

        foreach ($schemas as $mediaType => $members) {
            $merged = $merged->content($mediaType, count($members) === 1 ? $members[0] : $this->oneOf($members, $context));
        }

        return $merged;
    }

    /**
     * @param list<Schema> $members
     */
    private function oneOf(array $members, GenerationContext $context): Schema
    {
        $wrapped = array_map(static function (Schema $member): ?Schema {
            $properties = $member->properties ?? [];
            $data = $properties['data'] ?? null;

            return count($properties) === 1 && $data instanceof Schema ? $data : null;
        }, $members);

        // Resources under the same `data`: the alternatives are the resources themselves.
        if (! in_array(null, $wrapped, true)) {
            /** @var list<Schema> $wrapped */
            return Paginations::object(['data' => $this->alternatives($wrapped, $context)]);
        }

        return $this->alternatives($members, $context);
    }

    /**
     * @param list<Schema> $members
     */
    private function alternatives(array $members, GenerationContext $context): Schema
    {
        $schema = Schema::create()->oneOf(...$members);
        $names = array_map(static fn(Schema $member): ?string => is_string($member->ref) ? substr($member->ref, (int) strrpos($member->ref, '/') + 1) : null, $members);

        if (in_array(null, $names, true)) {
            return $schema;
        }

        /** @var list<string> $names */
        $common = null;

        foreach ($names as $name) {
            $properties = array_keys($context->components->get($name)->properties ?? []);
            $common = $common === null ? $properties : array_values(array_intersect($common, $properties));
        }

        foreach ($common ?? [] as $property) {
            $mapping = [];

            foreach ($names as $name) {
                $value = $this->resources->value($name, $property, $context);

                if (is_string($value) && ! isset($mapping[$value])) {
                    $mapping[$value] = "#/components/schemas/{$name}";
                }
            }

            if (count($mapping) === count($names)) {
                return $schema->discriminator(Discriminator::create()->propertyName($property)->mapping($mapping));
            }
        }

        return $schema;
    }
}
