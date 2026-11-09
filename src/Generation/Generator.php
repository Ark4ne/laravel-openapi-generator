<?php

declare(strict_types=1);

namespace Ark4ne\OpenApi\Generation;

use Ark4ne\OpenApi\Configuration\Configuration;
use Ark4ne\OpenApi\Configuration\DocumentConfiguration;
use Ark4ne\OpenApi\Configuration\Variant;
use Ark4ne\OpenApi\Context\GenerationContext;
use Ark4ne\OpenApi\Context\Stage;
use Ark4ne\OpenApi\Description\DocumentDescription;
use Ark4ne\OpenApi\Discovery\MiddlewareResolver;
use Ark4ne\OpenApi\Discovery\PathParameterResolver;
use Ark4ne\OpenApi\Discovery\RouteCollector;
use Ark4ne\OpenApi\Exceptions\InvalidConfigurationException;
use Ark4ne\OpenApi\Extraction\Callbacks\DeclaredCallbacks;
use Ark4ne\OpenApi\Extraction\Exceptions\ErrorBodies;
use Ark4ne\OpenApi\Extraction\Exceptions\LaravelErrorBodies;
use Ark4ne\OpenApi\Extraction\OperationContextFactory;
use Ark4ne\OpenApi\Extraction\Operations\OperationExtractors;
use Ark4ne\OpenApi\Extraction\Requests\RequestExtractors;
use Ark4ne\OpenApi\Extraction\Responses\OperationResponses;
use Ark4ne\OpenApi\Extraction\Rules\RuleMappers;
use Ark4ne\OpenApi\Extraction\Strategies\BuiltInStrategies;
use Ark4ne\OpenApi\Extraction\Strategies\QueryStrategy;
use Ark4ne\OpenApi\Sampling\Samplers;
use Ark4ne\OpenApi\Sampling\SamplingSession;
use Ark4ne\OpenApi\Types\TypeResolver;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Routing\Router;
use Throwable;

/**
 * Generates the documents of the configuration: for each document and each language, the routes, their
 * metadata, requests and responses inside one sampling session, the passes, then the writer's object.
 *
 * The available integrations add their resource formats and rule mappers, and the last one that has error bodies
 * replaces Laravel's; the extensions of the application join the registries, and its strategy classes come from the
 * container. The available variant integrations prepare the sample requests and describe the variant, in their order.
 */
final readonly class Generator
{
    /**
     * @param list<Integration> $integrations those not available are left out
     */
    public function __construct(
        private RouteCollector $routes,
        private Container $container,
        private SamplingSession $session,
        private TypeResolver $types,
        private ?Translator $translator = null,
        private DocumentBuilder $builder = new DocumentBuilder(),
        private array $integrations = [],
        private GeneratorExtensions $extensions = new GeneratorExtensions(),
    ) {}

    /**
     * @param list<Integration> $integrations
     */
    public static function make(Router $router, Container $container, SamplingSession $session, ?Translator $translator = null, array $integrations = [], GeneratorExtensions $extensions = new GeneratorExtensions()): self
    {
        return new self(new RouteCollector($router, new MiddlewareResolver($router), new PathParameterResolver()), $container, $session, new TypeResolver(), $translator, integrations: $integrations, extensions: $extensions);
    }

    /**
     * Every document of the configuration, in each of its languages (once without language when none is set) and each of
     * its variants (then its head, when it has one; once without variant when it has none).
     *
     * @return list<GeneratedDocument>
     */
    public function all(Configuration $configuration): array
    {
        $generated = [];

        foreach ($configuration->documents as $document) {
            foreach (self::variants($document) as $variant) {
                foreach ($configuration->languages === [] ? [null] : $configuration->languages as $locale) {
                    $generated[] = $this->generate($configuration, $document, $locale, $variant);
                }
            }
        }

        return $generated;
    }

    /**
     * @param Variant|null $variant the variant to generate, `null` for the head or a document without variants
     */
    public function generate(Configuration $configuration, string|DocumentConfiguration $document, ?string $locale = null, ?Variant $variant = null): GeneratedDocument
    {
        $document = is_string($document) ? $configuration->document($document) : $document;
        $integrations = array_values(array_filter($this->integrations, static fn(Integration $integration): bool => $integration->available()));
        $variants = array_values(array_filter($integrations, static fn(Integration $integration): bool => $integration instanceof VariantIntegration));
        $preparers = array_map(static fn(VariantIntegration $integration): Closure => $integration->prepareSampleRequest(...), $variants);
        $context = GenerationContext::for($configuration, $document, $locale, $variant, $preparers);
        $errors = self::errorBodies($integrations);
        $description = $this->session->run(fn(): DocumentDescription => $this->describe($context, $integrations, $errors)->addNotes(...self::notes($variants, $context)), $context);
        $description = Passes::default($configuration, $errors, $this->container, ...$this->extensions->passes)->process($description, $context);

        return new GeneratedDocument($this->builder->build($description, $context), $description, $context);
    }

    /**
     * The generations of a document in its variant dimension: each variant, then `null` for its head; only `null` for a
     * document without variants.
     *
     * @return list<Variant|null>
     */
    public static function variants(DocumentConfiguration $document): array
    {
        $variants = $document->variants;

        if ($variants === null) {
            return [null];
        }

        return $variants->head === null ? $variants->values : [...$variants->values, null];
    }

    /**
     * Builds what the configuration names for a document (strategy classes, middleware mappers) without generating it,
     * so that an invalid class is reported before any document is written.
     *
     * @throws InvalidConfigurationException
     */
    public function prepare(Configuration $configuration, DocumentConfiguration $document): void
    {
        $this->operations($document);
        $this->query($document);
        Passes::default($configuration, new LaravelErrorBodies(), $this->container);
    }

    /**
     * @throws InvalidConfigurationException
     */
    private function operations(DocumentConfiguration $document): OperationExtractors
    {
        $key = "documents.{$document->name}.strategies";

        return OperationExtractors::default(
            BuiltInStrategies::summary($document->summary, "{$key}.summary", $this->container),
            BuiltInStrategies::tags($document->tags, "{$key}.tags", $this->container),
            BuiltInStrategies::operationId($document->operationId, "{$key}.operation_id", $this->container),
            $this->translator,
            BuiltInStrategies::groups($document->groups, "{$key}.groups", $this->container),
        )->with(...$this->extensions->operationExtractors);
    }

    /**
     * @throws InvalidConfigurationException
     */
    private function query(DocumentConfiguration $document): QueryStrategy
    {
        return BuiltInStrategies::query($document->query, "documents.{$document->name}.strategies.query", $this->container);
    }

    /**
     * The error bodies of the last integration that has some, Laravel's otherwise.
     *
     * @param list<Integration> $integrations
     */
    private static function errorBodies(array $integrations): ErrorBodies
    {
        $errors = new LaravelErrorBodies();

        foreach ($integrations as $integration) {
            $errors = $integration->errorBodies() ?? $errors;
        }

        return $errors;
    }

    /**
     * What the variant integrations describe of the variant, in their order; one that throws is reported.
     *
     * @param list<VariantIntegration> $integrations the available ones
     *
     * @return list<string>
     */
    private static function notes(array $integrations, GenerationContext $context): array
    {
        $notes = [];

        foreach ($integrations as $integration) {
            try {
                $note = $integration->describe($context);
            } catch (Throwable $error) {
                $context->diagnostics->warning(Stage::Pass, "cannot describe the variant {$context->variantName()}: {$error->getMessage()}");

                continue;
            }

            if ($note !== null) {
                $notes[] = $note;
            }
        }

        return $notes;
    }

    /**
     * @param list<Integration> $integrations the available ones
     */
    private function describe(GenerationContext $context, array $integrations, ErrorBodies $errors): DocumentDescription
    {
        $configuration = $context->document;
        $factory = new OperationContextFactory($this->types);
        $operations = $this->operations($configuration);
        $formats = array_merge(...array_map(static fn(Integration $integration): array => $integration->resourceFormats(), $integrations));
        $mappers = array_merge(...array_map(static fn(Integration $integration): array => $integration->ruleMappers(), $integrations));
        $samplers = Samplers::default($this->session, $this->types)->with(...$this->extensions->samplers);
        $responses = OperationResponses::default($samplers, $this->types, $formats, $errors, $this->extensions->responseExtractors);
        $requests = RequestExtractors::default($this->container, $this->query($configuration), RuleMappers::default()->with(...$mappers, ...$this->extensions->ruleMappers), $this->types, $responses->resources())
            ->with(...$this->extensions->requestExtractors);
        $callbacks = new DeclaredCallbacks($responses->declared());
        $document = DocumentDescription::create();

        foreach ($this->routes->collect($context) as $route) {
            $operation = $factory->create($route, $context);
            $description = $operations->extract($operation, $operation->describe(), $context);
            $description = $description
                ->request($requests->extract($operation, $description->request, $context))
                ->responses(...$responses->describe($operation, $context))
                ->callbacks(...$callbacks->describe($operation, $context));

            // A webhook is filed apart from the start: the passes tied to the routes never see it.
            $document = match (true) {
                $description->excluded => $document,
                $description->webhook !== null => $document->addWebhooks($description),
                default => $document->addOperations($description),
            };
        }

        return $document;
    }
}
